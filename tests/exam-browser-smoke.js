/* Local integration test: requires Playwright, Chromium, PHP/PDO and the local DB. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const base = process.env.NISHA_TEST_BASE || 'http://127.0.0.1:8000';
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Run this account-creating test against local development only.');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const exams = JSON.parse(execFileSync(php, ['-r', 'echo json_encode(require "includes/exam-data.php");'], { cwd: root, encoding: 'utf8' }));
const artifactDir = fs.mkdtempSync('/tmp/nisha-full-mock-check-');
let browser, context, page, fixtureEmail;
let checks = 0;
const errors = [];
function ok(condition, label) { assert(condition, label); checks++; console.log('PASS', label); }
async function attach(storageState) {
    context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, permissions: ['microphone'], acceptDownloads: true, ...(storageState ? { storageState } : {}) });
    page = await context.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
}
async function currentState(examId) {
    return page.evaluate((id) => {
        const data = JSON.parse(document.querySelector('#exam-data').textContent);
        const key = `nisha:fullmock:${data.userScope}:${id}:${data.exams[id].version}`;
        return { key, state: key ? JSON.parse(localStorage.getItem(key)) : null };
    }, examId);
}
async function start(examId) {
    await page.goto(`${base}/exams.php?exam=${examId}&start=1`);
    await page.locator('#exam-setup-dialog').waitFor({ state: 'visible' });
    await page.locator('#assisted-mode').check(); await page.locator('#ready-confirmed').check();
    await page.locator('#begin-exam').click();
    await page.locator('#exam-runner:not([hidden])').waitFor();
}
async function useFixture(examId, patch, speechMock = false) {
    const saved = await currentState(examId);
    assert(saved.state, 'A real UI-started state is needed for a fixture');
    const snapshot = await context.storageState();
    const origin = snapshot.origins.find((entry) => entry.origin === new URL(base).origin);
    const entry = origin.localStorage.find((value) => value.name === saved.key);
    const now = Date.now();
    const next = { ...saved.state, attemptId: crypto.randomUUID(), phase: 'running', startedAt: now, expiresAt: now + 48 * 3600000, sectionIndex: 0, itemIndex: 0, sectionDeadline: now + exams[examId].sections[0].duration_seconds * 1000, answers: {}, flags: [], played: [], audioEnded: {}, itemClocks: {}, completedSections: [], endedAt: null, ...patch };
    delete next.resultEnvelope;
    entry.value = JSON.stringify(next);
    await context.close(); await attach(snapshot);
    if (speechMock) {
        await page.addInitScript(() => {
            class MockUtterance extends EventTarget { constructor(text) { super(); this.text = text; } }
            Object.defineProperty(window, 'SpeechSynthesisUtterance', { value: MockUtterance, configurable: true });
            window.__spokenScripts = [];
            Object.defineProperty(window, 'speechSynthesis', { configurable: true, value: {
                getVoices: () => [{ lang: 'en-GB' }], cancel() {},
                speak(utterance) { window.__spokenScripts.push(utterance.text); setTimeout(() => utterance.dispatchEvent(new Event('start')), 15); setTimeout(() => utterance.dispatchEvent(new Event('end')), 50); },
            } });
        });
    }
    await page.goto(`${base}/exams.php?exam=${examId}&start=1`);
    return next;
}
async function acceptDialog() { await page.locator('#exam-confirm-dialog[open]').waitFor(); await page.locator('#exam-confirm-yes').click(); }
async function fillItem(item) {
    if (item.type === 'short') await page.locator('.exam-short-input').fill(item.answer[0]);
    else if (item.type === 'choice' || item.type === 'multi') {
        for (const id of Array.isArray(item.answer) ? item.answer : [item.answer]) await page.locator(`.exam-choice:has(input[value="${id}"])`).click();
        if (item.text) ok((await page.locator('#exam-resource-panel').innerText()).includes(item.text.slice(0, 50)), item.id + ' reading stimulus visible');
    } else if (item.type === 'gaps') {
        for (const blank of item.blanks) {
            const field = page.getByLabel('Blank ' + blank.id, { exact: true });
            if (blank.options) await field.selectOption(blank.answer[0]); else await field.fill(blank.answer[0]);
        }
    } else if (item.type === 'reorder') {
        const order = item.options.map((option) => option.id);
        for (let target = 0; target < item.answer.length; target++) {
            let index = order.indexOf(item.answer[target]);
            while (index > target) {
                await page.locator(`.exam-reorder button[data-option-id="${order[index]}"][data-move="-1"]`).click();
                [order[index], order[index - 1]] = [order[index - 1], order[index]]; index--;
            }
        }
        const accept = page.getByRole('button', { name: 'Use this order', exact: true });
        if (await accept.count()) await accept.click();
    } else if (item.type === 'highlight') {
        for (const index of item.answer) await page.locator('.exam-highlight-words button').nth(Number(index)).click();
    } else if (item.type === 'essay') {
        await page.locator('.exam-writing-area').fill('Education and community resources can improve access when services are designed around the people who use them. This is a test response for ungraded reflection.');
        ok((await page.locator('.exam-writing-meta').innerText()).includes('words'), item.id + ' word counter visible');
    } else if (item.type === 'spoken') {
        await page.locator('.exam-speaking-notes').fill('Self-review: speak steadily, give a concrete example, and check pronunciation.');
    }
}
async function finishEveryItem(examId) {
    const exam = exams[examId];
    for (let sectionIndex = 0; sectionIndex < exam.sections.length; sectionIndex++) {
        const section = exam.sections[sectionIndex];
        for (let index = 0; index < section.items.length; index++) {
            const item = section.items[index];
            await page.locator('#item-title').waitFor({ state: 'visible' });
            const title = item.prompt || item.title || item.task_type;
            await page.waitForFunction(expected => document.querySelector('#item-title').textContent === expected, title, { timeout: 5000 });
            assert.equal(await page.locator('#item-title').innerText(), title, item.id + ' is the current task');
            if (examId === 'ielts-academic-01' && sectionIndex === 1 && index === 1) {
                ok(await page.locator('.exam-passage').evaluate(node => node.scrollTop > 0), 'Reading passage keeps its scroll position between questions');
            }
            await fillItem(item);
            if (examId === 'ielts-academic-01' && sectionIndex === 1 && index === 0) {
                await page.locator('.exam-passage').evaluate(node => { node.scrollTop = 160; });
            }
            if (item.type === 'essay' && item.resource_id && exam.resources[item.resource_id]?.kind === 'chart') ok(await page.locator('.exam-chart-table').count() === 1, 'IELTS Writing Task 1 chart rendered');
            await page.locator('#next-item').click();
            if (index === section.items.length - 1) await acceptDialog();
        }
    }
}
(async () => {
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox', '--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] });
    await attach();
    await page.goto(`${base}/exams.php`);
    ok(await page.locator('.full-exam-card').count() === 2, 'Both full mocks available');
    const containsAnswer = await page.evaluate(() => {
        const visit = value => value && typeof value === 'object' && Object.entries(value).some(([key, child]) => ['answer', 'explanation', 'correct_text'].includes(key) || visit(child));
        return Boolean(visit(JSON.parse(document.querySelector('#exam-data').textContent)));
    });
    ok(!containsAnswer, 'Public candidate data contains no answer keys');
    await start('ielts-academic-01');
    await page.locator('.exam-short-input').fill('Hartley'); await page.locator('#flag-item').click();
    await page.locator('#next-item').click(); await page.locator('#previous-item').click();
    ok(await page.locator('.exam-short-input').inputValue() === 'Hartley', 'IELTS answer survives question navigation');
    const before = await currentState('ielts-academic-01'); await page.reload();
    ok(await page.locator('.exam-short-input').inputValue() === 'Hartley', 'Reload restores entered answer');
    const after = await currentState('ielts-academic-01');
    ok(after.state.sectionDeadline === before.state.sectionDeadline && after.state.flags.includes('il01'), 'Reload keeps deadline and review flag');
    let fail = true;
    await page.route('**/api/complete-exam.php', route => { if (fail) { fail = false; return route.abort(); } return route.continue(); });
    await finishEveryItem('ielts-academic-01');
    await page.locator('#submission-message').filter({ hasText: 'retained' }).waitFor();
    ok((await currentState('ielts-academic-01')).state.answers.il01 === 'hartley', 'Failed submission retains all answers');
    await page.locator('#retry-submission').click(); await page.locator('#exam-result:not([hidden])').waitFor();
    ok((await page.locator('#exam-result-points').innerText()).replace(/\s/g, '') === '80/80', 'Entire IELTS exam reaches real server feedback with 80/80');
    ok(await page.locator('.exam-review-item').count() === 85, 'IELTS review contains all 85 tasks');
    const downloadPromise = page.waitForEvent('download'); await page.locator('#download-exam-result').click(); const download = await downloadPromise;
    await download.saveAs(path.join(artifactDir, 'ielts-result.json'));
    const result = JSON.parse(fs.readFileSync(path.join(artifactDir, 'ielts-result.json'), 'utf8'));
    ok(result.result.sections[2].ungraded_items === 2 && result.result.sections[3].ungraded_items === 3, 'Writing and speaking remain ungraded in downloaded report');
    await page.screenshot({ path: path.join(artifactDir, 'ielts-result.png'), fullPage: true });
    await page.goto(`${base}/register.php`);
    fixtureEmail = `full-mock-browser-${Date.now()}@example.test`;
    const password = crypto.randomBytes(20).toString('hex');
    await page.locator('#name').fill('Mock Browser Check'); await page.locator('#email').fill(fixtureEmail);
    await page.locator('#password').fill(password); await page.locator('#password_confirmation').fill(password);
    await page.locator('button[type="submit"]').click(); await page.waitForURL('**/dashboard.php');
    await start('pte-academic-01');
    ok(await page.locator('#previous-item').isHidden() && await page.locator('#question-map-wrap').isHidden(), 'PTE exposes forward-only navigation');
    let fakeUnsaved = true;
    await page.route('**/api/complete-exam.php', async route => {
        if (!fakeUnsaved) return route.continue(); fakeUnsaved = false;
        const response = await route.fetch(); const body = await response.json();
        body.saved = false; body.result_id = null; body.save_message = 'Simulated lost save acknowledgement: retry with the same attempt.';
        await route.fulfill({ response, json: body });
    });
    await finishEveryItem('pte-academic-01'); await page.locator('#exam-result:not([hidden])').waitFor();
    ok((await page.locator('#exam-result-points').innerText()).replace(/\s/g, '') === '82/82', 'Entire PTE exam reaches real server feedback with 82/82');
    ok(await page.locator('.exam-review-item').count() === 68, 'PTE review contains all 68 tasks');
    ok(await page.locator('#retry-save-result').isVisible(), 'Unsaved signed-in result offers account retry');
    await page.locator('#retry-save-result').click(); await page.waitForFunction(() => document.querySelector('#exam-result-save').textContent.includes('Your account result is saved'));
    ok(await page.locator('#retry-save-result').isHidden(), 'Idempotent retry saves without duplicating or losing review');
    await page.goto(`${base}/dashboard.php`);
    ok((await page.locator('.mock-history').innerText()).includes('82/82'), 'Student dashboard shows saved full-mock points');
    await page.locator('.mock-review').first().click(); await page.locator('#exam-result:not([hidden])').waitFor();
    ok((await page.locator('#exam-result-points').innerText()).replace(/\s/g, '') === '82/82', 'Saved report reopens through owner-only result endpoint');
    await page.screenshot({ path: path.join(artifactDir, 'pte-result.png'), fullPage: true });
    const pte = exams['pte-academic-01'];
    await useFixture(pte.id, { sectionIndex: 1, itemIndex: 7, sectionDeadline: Date.now() + 1500000 });
    ok(await page.getByRole('button', { name: 'Use this order', exact: true }).isVisible(), 'Reorder task requires explicit order entry');
    const reorderId = pte.sections[1].items[7].id;
    await page.locator('#next-item').click();
    ok(!(await currentState(pte.id)).state.answers[reorderId], 'Skipping reorder does not invent an answer or partial credit');
    const nearDeadline = Date.now() + 60000;
    await useFixture(pte.id, { sectionIndex: 1, itemIndex: 7, sectionDeadline: nearDeadline });
    await page.clock.install();
    await page.clock.setSystemTime(new Date(nearDeadline + 1000));
    await page.locator('.exam-reorder button:not([disabled])').first().click();
    ok((await page.locator('#runner-title').innerText()).includes('Listening') && await page.locator('.exam-reorder').count() === 0, 'Late reorder interaction cannot redraw an expired section');
    const swtIndex = pte.sections[0].items.findIndex(item => item.task_type === 'Summarize Written Text');
    const swtId = pte.sections[0].items[swtIndex].id;
    await useFixture(pte.id, { sectionIndex: 0, itemIndex: swtIndex, answers: { [swtId]: 'Retained summary.' }, sectionDeadline: Date.now() + 4800000, itemClocks: { [swtId]: { prepDeadline: Date.now() - 601000, responseDeadline: Date.now() - 1000 } } });
    ok((await page.locator('#item-counter').innerText()).includes(String(swtIndex + 2)), 'Expired PTE summary limit advances to next task');
    ok((await currentState(pte.id)).state.answers[swtId] === 'Retained summary.', 'Timed-out writing remains retained');
    const repeatIndex = pte.sections[0].items.findIndex(item => item.task_type === 'Repeat Sentence');
    const repeat = pte.sections[0].items[repeatIndex];
    await useFixture(pte.id, { assisted: false, sectionIndex: 0, itemIndex: repeatIndex, sectionDeadline: Date.now() + 4800000 }, true);
    ok(!(await currentState(pte.id)).state.itemClocks[repeat.id], 'Spoken response clock waits for its audio stimulus');
    await page.locator('#stimulus-play').click(); await page.waitForFunction(() => document.querySelector('#stimulus-status').textContent.startsWith('Audio complete'));
    ok(Boolean((await currentState(pte.id)).state.itemClocks[repeat.id]), 'Response timing begins after audio ends');
    ok(await page.locator('#stimulus-play').isDisabled(), 'Exam audio cannot be replayed');
    await page.clock.install(); await page.clock.fastForward((repeat.prep_seconds || 0) * 1000 + 500);
    await page.locator('[data-record]').click(); await page.waitForFunction(() => document.querySelector('[data-record-status]').textContent.includes('Recording'));
    await page.waitForTimeout(1100); await page.locator('[data-record]').click();
    await page.locator('.exam-recording-playback audio').waitFor();
    ok(await page.locator('.exam-recording-playback a[download]').count() === 1, 'Speaking produces local playback and download');
    const endCueIndex = pte.sections[2].items.findIndex(item => item.task_type === 'Select Missing Word');
    await useFixture(pte.id, { assisted: false, sectionIndex: 2, itemIndex: endCueIndex, sectionDeadline: Date.now() + 2100000 }, true);
    await page.locator('#stimulus-play').click(); await page.waitForFunction(() => document.querySelector('#stimulus-status').textContent.startsWith('Audio complete'));
    ok(await page.evaluate(() => window.__spokenScripts.every(text => !text.includes('[tone]'))), 'Missing-word stimulus omits placeholder from spoken audio');
    for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No exam-room horizontal overflow at ' + width + 'px');
    }
    await page.screenshot({ path: path.join(artifactDir, 'exam-mobile.png'), fullPage: true });
    await start('ielts-academic-01');
    const fullBudget = 9840000; const startedAt = Date.now() - fullBudget - 5000;
    await useFixture('ielts-academic-01', { startedAt, sectionDeadline: startedAt + 1800000 });
    await page.locator('#exam-result:not([hidden])').waitFor();
    ok((await page.locator('#exam-result-points').innerText()).replace(/\s/g, '') === '0/80', 'Resume catches up every expired section without resetting timers');
    ok((await currentState('ielts-academic-01')).state.resultEnvelope.result.completed_sections.length === 4, 'All expired sections finish exactly once');
    ok(errors.length === 0, 'No browser JavaScript errors');
    console.log(`PASS: ${checks} browser checks; artifacts ${artifactDir}`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (context) await context.close(); if (browser) await browser.close();
    if (fixtureEmail) {
        execFileSync(php, ['-r', 'require "includes/bootstrap.php"; $s=db()->prepare("DELETE FROM users WHERE email = ?"); $s->execute([$argv[1]]);', fixtureEmail], { cwd: root, stdio: 'pipe' });
    }
});
