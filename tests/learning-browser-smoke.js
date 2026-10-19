/* Local integration: PHP on port8000, Playwright and Chromium. No accounts created. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const base = process.env.NISHA_TEST_BASE || 'http://127.0.0.1:8000';
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Run this test against local development only.');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const canonical = JSON.parse(execFileSync(php, ['-r', `
require 'includes/learning-catalog.php';
$courses=nisha_learning_catalog();$sessions=[];
foreach($courses as $course){
    $ids=[$course['full_exam_id']];
    foreach($course['modules'] as $module){$ids[]=$module['practice_id'];}
    foreach($course['sections'] as $section){$ids[]=$section['exam_id'];}
    foreach($ids as $id){$sessions[$id]=nisha_resolve_exam($id);}
}
echo json_encode(['courses'=>$courses,'sessions'=>$sessions],JSON_THROW_ON_ERROR);
`], { cwd: root, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 }));
const artifactDir = fs.mkdtempSync('/tmp/nisha-learning-check-');
let browser, context, page;
let checks = 0;
const errors = [];
const externalRequests = [];
function ok(condition, label) { assert(condition, label); checks++; console.log('PASS', label); }
const items = session => session.sections.flatMap(section => section.items);
const firstItem = id => items(canonical.sessions[id])[0];
function correctResponse(item) {
    if (item.type === 'short') return item.answer[0];
    if (item.type === 'gaps') return Object.fromEntries(item.blanks.map(blank => [blank.id, blank.answer[0]]));
    if (['essay', 'spoken'].includes(item.type)) return 'An original response for self-review.';
    return item.answer;
}
function emptyResponse(item) { return ['multi', 'reorder', 'highlight'].includes(item.type) ? [] : item.type === 'gaps' ? {} : ''; }
function privateKeys(value) {
    return value && typeof value === 'object' && Object.entries(value).some(([key, child]) => ['answer', 'explanation', 'correct_text'].includes(key) || privateKeys(child));
}
async function savedState(id) {
    return page.evaluate(sessionId => {
        for (const key of Object.keys(localStorage)) {
            try {
                const state = JSON.parse(localStorage.getItem(key));
                if (state?.examId === sessionId) return { key, state };
            } catch (_) { /* Unrelated storage does not belong to this runner. */ }
        }
        return null;
    }, id);
}
async function patchState(id, patch) {
    const saved = await savedState(id);
    assert(saved, 'A real UI-started state is required');
    // Apply after pagehide's normal autosave and before application restoration.
    // The one-use marker prevents this fixture from changing future reloads.
    await page.addInitScript(({ key, state, patch, marker }) => {
        if (sessionStorage.getItem(marker)) return;
        localStorage.setItem(key, JSON.stringify({ ...state, ...patch }));
        sessionStorage.setItem(marker, 'used');
    }, { ...saved, patch, marker: `learning-fixture-${Date.now()}-${Math.random()}` });
    await page.reload();
}
async function start(id) {
    await page.goto(`${base}/exams.php?exam=${encodeURIComponent(id)}&start=1`);
    if (await page.locator('#exam-runner').isVisible()) return;
    await page.locator('#exam-setup-dialog').waitFor({ state: 'visible' });
    if (!(await page.locator('#assisted-mode').isChecked())) await page.locator('#assisted-mode').check();
    await page.locator('#ready-confirmed').check();
    await page.locator('#begin-exam').click();
    await page.locator('#exam-runner:not([hidden])').waitFor();
}
async function fillItem(item, response = correctResponse(item)) {
    if (item.type === 'short') await page.locator('.exam-short-input').fill(response);
    else if (item.type === 'choice' || item.type === 'multi') {
        for (const id of Array.isArray(response) ? response : [response]) await page.locator(`.exam-choice:has(input[value="${id}"])`).click();
    } else if (item.type === 'gaps') {
        for (const blank of item.blanks) {
            const field = page.getByLabel('Blank ' + blank.id, { exact: true });
            if (blank.options) await field.selectOption(response[blank.id] || '');
            else await field.fill(response[blank.id] || '');
        }
    } else if (item.type === 'essay') await page.locator('.exam-writing-area').fill(response);
    else if (item.type === 'spoken') await page.locator('.exam-speaking-notes').fill(response);
}
async function feedback(payload, csrf = true) {
    return page.evaluate(async ({ payload, csrf }) => {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch('api/practice-feedback.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-Token': token } : {}) },
            body: JSON.stringify(payload),
        });
        return { status: response.status, body: await response.json() };
    }, { payload, csrf });
}
async function installSpeechMock() {
    await page.evaluate(() => {
        class MockUtterance extends EventTarget {
            constructor(text) { super(); this.text = text; }
        }
        Object.defineProperty(window, 'SpeechSynthesisUtterance', { value: MockUtterance, configurable: true });
        window.__speechCheck = { cancellations: 0, scripts: [] };
        Object.defineProperty(window, 'speechSynthesis', { configurable: true, value: {
            getVoices: () => [{ lang: 'en-GB' }],
            cancel: () => { window.__speechCheck.cancellations++; },
            speak: utterance => {
                window.__speechCheck.scripts.push(utterance.text);
                setTimeout(() => utterance.dispatchEvent(new Event('start')), 0);
                // Hold the utterance open so navigation can prove cancellation.
            },
        } });
    });
}
const feedbackPayload = (session, item, response) => ({ exam_id: session.id, exam_version: session.version, item_id: item.id, response });

(async () => {
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.route('**/*', route => {
        const url = new URL(route.request().url());
        if (['http:', 'https:'].includes(url.protocol) && url.origin !== new URL(base).origin) {
            externalRequests.push(url.origin); return route.abort();
        }
        return route.continue();
    });
    page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));

    await page.goto(`${base}/practice.php`);
    ok(await page.locator('#learning-course-tabs [data-course-id]').count() === 4, 'Practice library exposes all four course choices');
    for (const course of canonical.courses) {
        await page.locator(`#learning-course-tabs [data-course-id="${course.id}"]`).click();
        await page.locator(`#learning-course-tabs [data-course-id="${course.id}"][aria-current="page"]`).waitFor();
        const cards = page.locator(`.learning-course-panel[data-course="${course.id}"] .learning-module:visible`);
        ok(await cards.count() === course.modules.length, `${course.name} exposes its real ${course.modules.length} modules`);
        ok(await page.locator('.learning-full-link:visible').count() === 1, `${course.name} has a full-mock launch`);
        ok(await page.locator('.learning-sectional-link:visible').count() === course.sections.length, `${course.name} exposes every sectional test`);
    }

    const course = canonical.courses.find(entry => entry.id === 'ielts-academic');
    await page.locator(`#learning-course-tabs [data-course-id="${course.id}"]`).click();
    await page.locator('#learning-skill-filters [data-skill="reading"]').click();
    const visibleSkills = await page.locator('.learning-module:visible').evaluateAll(cards => cards.map(card => card.dataset.skill));
    ok(visibleSkills.length > 0 && visibleSkills.every(skill => skill === 'reading'), 'Skill filter shows reading modules only');
    await page.locator('#learning-search').fill('zzzz-no-learning-result-zzzz');
    await page.locator('#learning-search-form').evaluate(form => form.requestSubmit());
    await page.locator('#learning-empty').waitFor({ state: 'visible' });
    ok(await page.locator('.learning-module:visible').count() === 0, 'Search exposes an honest empty state');
    await page.locator('#learning-clear-filters').click();
    ok(await page.locator('.learning-module:visible').count() === course.modules.length, 'Clearing filters restores the selected course');
    const readingModule = course.modules.find(module => module.skill === 'reading');
    await page.locator('#learning-search').fill(readingModule.title);
    await page.locator('#learning-search-form').evaluate(form => form.requestSubmit());
    ok(await page.locator('.learning-module:visible').count() > 0, 'Searching a real task title finds a launchable module');
    const linkedId = new URL(await page.locator('.learning-module:visible .learning-module-link').first().getAttribute('href'), base).searchParams.get('exam');
    ok(Boolean(canonical.sessions[linkedId]), 'Practice card links to a resolver-approved session');
    await page.setViewportSize({ width: 390, height: 844 });
    ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Mobile library and filters fit the viewport');
    await page.screenshot({ path: path.join(artifactDir, 'learning-mobile.png'), fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });

    await page.goto(`${base}/exams.php`);
    ok(await page.locator('.full-exam-card').count() === 4, 'Full-mock catalogue contains four distinct courses');
    const publicData = await page.locator('#exam-data').textContent();
    ok(!privateKeys(JSON.parse(publicData)), 'Candidate catalogue data contains no answer keys or explanations');

    const practiceModule = course.modules.find(module => firstItem(module.practice_id).type === 'short');
    assert(practiceModule, 'A real typed-answer module is required for guided UI checks');
    const practiceId = practiceModule.practice_id;
    const question = firstItem(practiceId);
    await start(practiceId);
    ok((await page.locator('#section-clock').innerText()).trim() === 'Untimed', 'Guided practice labels its clock Untimed');
    ok(await page.locator('#item-time-panel').isHidden() && await page.locator('#check-practice-answer').isVisible(), 'Guided practice hides task deadlines and offers Check answer');
    await fillItem(question);
    let failOnce = true;
    await page.route('**/api/practice-feedback.php', route => {
        if (failOnce) { failOnce = false; return route.abort(); }
        return route.continue();
    });
    await page.locator('#check-practice-answer').click();
    await page.locator('#practice-feedback').waitFor({ state: 'visible' });
    ok(await page.locator('.exam-short-input').inputValue() === question.answer[0], 'Feedback connection failure retains the typed response');
    await page.locator('#check-practice-answer').click();
    await page.waitForFunction(explanation => document.querySelector('#practice-feedback').textContent.includes(explanation), question.explanation);
    ok((await page.locator('#practice-feedback').innerText()).includes(question.explanation), 'Check answer shows actual server explanation for the current question');
    const saved = await savedState(practiceId);
    await patchState(practiceId, {
        sectionDeadline: Date.now() - 10000000,
        itemClocks: { [question.id]: { prepDeadline: Date.now() - 100000, responseDeadline: Date.now() - 1000 } },
    });
    await page.locator('#exam-runner:not([hidden])').waitFor();
    ok(await page.locator('.exam-short-input').inputValue() === question.answer[0], 'Untimed practice restores its response after reload despite old deadline fields');
    ok((await savedState(practiceId)).state.itemIndex === saved.state.itemIndex && (await page.locator('#section-clock').innerText()).trim() === 'Untimed', 'Untimed practice never advances from elapsed section/task deadline fields');
    await page.unroute('**/api/practice-feedback.php');

    // Every catalogue module sends a real canonical response to the endpoint.
    // The separate pure suite additionally checks every item, distractors and skips.
    let formatsChecked = 0;
    const interactions = new Set();
    for (const currentCourse of canonical.courses) {
        for (const module of currentCourse.modules) {
            const session = canonical.sessions[module.practice_id];
            const item = items(session)[0];
            const response = await feedback(feedbackPayload(session, item, correctResponse(item)));
            assert.equal(response.status, 200, `${module.practice_id}: feedback HTTP status`);
            assert.equal(response.body.success, true, `${module.practice_id}: feedback success`);
            const review = response.body.review;
            assert.equal(review.id, item.id, `${module.practice_id}: feedback must be scoped to requested item`);
            if (['essay', 'spoken'].includes(item.type)) assert(review.graded === false && review.score === null && review.max_score === null, 'Ungraded responses stay ungraded');
            else assert(review.max_score > 0 && review.score === review.max_score, `${module.practice_id}: canonical response earns available points`);
            assert(!response.body.result && !response.body.responses, 'Feedback must not expose another question or result');
            interactions.add(item.type); formatsChecked++;
        }
    }
    ok(interactions.size === 8, 'Instant feedback covers all eight response interactions');
    ok(formatsChecked === canonical.courses.reduce((sum, entry) => sum + entry.modules.length, 0), `All ${formatsChecked} real course modules receive valid scoped feedback`);
    const session = canonical.sessions[practiceId];
    let response = await feedback(feedbackPayload(session, question, emptyResponse(question)));
    ok(response.status === 200 && response.body.review.score === 0, 'Empty practice answer produces zero points without inventing a response');
    response = await feedback(feedbackPayload(session, question, 'nisha-incorrect-answer'));
    ok(response.status === 200 && response.body.review.score === 0, 'Wrong practice answer produces zero points');
    response = await feedback(feedbackPayload(session, question, correctResponse(question)), false);
    ok(response.status === 403, 'Instant feedback rejects missing CSRF');
    response = await feedback({ ...feedbackPayload(session, question, correctResponse(question)), exam_version: 'stale-version' });
    ok(response.status === 409 && response.body.code === 'exam_version_changed', 'Instant feedback rejects a stale content version');
    for (const id of [course.full_exam_id, course.sections[0].exam_id]) {
        const target = canonical.sessions[id];
        response = await feedback(feedbackPayload(target, items(target)[0], correctResponse(items(target)[0])));
        ok(response.status === 403, `${target.session_type} mock cannot reveal answers through instant feedback`);
    }

    // A sectional mock keeps a real wall-clock deadline, then submits only its section.
    const sectionId = course.sections.find(section => /reading/i.test(section.title)).exam_id;
    await start(sectionId);
    ok((await page.locator('#section-clock').innerText()).trim() !== 'Untimed' && await page.locator('#check-practice-answer').isHidden(), 'Sectional mock retains its countdown and hides instant checking');
    const before = await savedState(sectionId);
    await page.reload();
    ok((await savedState(sectionId)).state.sectionDeadline === before.state.sectionDeadline, 'Sectional reload preserves its original deadline');
    await patchState(sectionId, { sectionDeadline: Date.now() - 2000 });
    await page.locator('#exam-result:not([hidden])').waitFor();
    const sectionResult = (await savedState(sectionId)).state.resultEnvelope.result;
    ok(sectionResult.session_type === 'section' && sectionResult.sections.length === 1 && sectionResult.objective_total === 40, 'Expired sectional mock grades only its real /40 reading section');

    // Exercise new visual resource renderers through real practice modules.
    const visualSessions = new Map();
    for (const module of course.modules) {
        const candidate = canonical.sessions[module.practice_id];
        const current = items(candidate)[0];
        for (const resourceId of [current.resource_id, current.secondary_resource_id]) {
            const resource = candidate.resources[resourceId];
            if (!resource) continue;
            const kind = resource.kind === 'chart' ? resource.chart?.type || 'bar' : resource.kind;
            if (['diagram', 'table', 'line', 'pie'].includes(kind)) visualSessions.set(kind, candidate.id);
        }
    }
    for (const kind of ['diagram', 'table', 'line', 'pie']) {
        assert(visualSessions.has(kind), `A real ${kind} visual practice session is required`);
        await start(visualSessions.get(kind));
        const selector = kind === 'diagram' ? '.exam-diagram svg' : kind === 'table' ? '.exam-data-table-wrap table' : '.exam-chart svg';
        ok(await page.locator(selector).count() > 0, `Real ${kind} resource renders in the practice runner`);
        await page.setViewportSize({ width: 390, height: 844 });
        ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${kind} practice fits a mobile viewport`);
        await page.setViewportSize({ width: 1440, height: 1000 });
    }
    await page.screenshot({ path: path.join(artifactDir, 'learning-visual.png'), fullPage: true });

    await start(practiceId);
    await installSpeechMock();
    await page.locator('#stimulus-play').click();
    await page.waitForFunction(() => document.querySelector('#stimulus-status').textContent.startsWith('Audio is playing'));
    let cancelCount = await page.evaluate(() => window.__speechCheck.cancellations);
    await page.locator('#next-item').click();
    ok(await page.evaluate(() => window.__speechCheck.cancellations) === cancelCount, 'IELTS navigation within the same recording preserves ongoing audio');

    const pteCourse = canonical.courses.find(entry => entry.id === 'pte-academic');
    const repeatModule = pteCourse.modules.find(module => module.title === 'Repeat Sentence');
    await start(repeatModule.practice_id);
    await installSpeechMock();
    await page.locator('#stimulus-play').click();
    await page.waitForFunction(() => document.querySelector('#stimulus-status').textContent.startsWith('Audio is playing'));
    cancelCount = await page.evaluate(() => window.__speechCheck.cancellations);
    await page.locator('#next-item').click();
    ok(await page.evaluate(() => window.__speechCheck.cancellations) > cancelCount && await page.locator('#stimulus-play').isEnabled(), 'PTE navigation to a new stimulus cancels the previous audio');

    const readAloud = pteCourse.modules.find(module => module.title === 'Read Aloud');
    await start(readAloud.practice_id);
    await fillItem(firstItem(readAloud.practice_id));
    await page.locator('#submit-exam-early').click();
    await page.locator('#exam-confirm-dialog[open]').waitFor();
    await page.locator('#exam-confirm-yes').click();
    await page.locator('#exam-result:not([hidden])').waitFor();
    ok((await page.locator('#exam-result-points').innerText()).trim() === 'Self-review', 'Speaking-only practice displays Self-review instead of fabricated points');

    for (const id of ['ielts-general-01', 'pte-core-01']) {
        await start(id);
        ok((await page.locator('#runner-exam-name').innerText()).includes(canonical.sessions[id].name) && (await page.locator('#section-clock').innerText()).trim() !== 'Untimed', `${canonical.sessions[id].name} launches as a timed full mock`);
    }
    ok(errors.length === 0, 'Learning pages have no browser JavaScript errors');
    ok(externalRequests.length === 0, 'Learning flow needs no external network requests');
    console.log(`PASS: ${checks} learning browser checks; artifacts ${artifactDir}`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (context) await context.close();
    if (browser) await browser.close();
});
