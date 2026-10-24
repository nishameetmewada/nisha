/* Local generated-library integration. No paid generation, AI or payment calls. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const base = process.env.NISHA_TEST_BASE || 'http://127.0.0.1:8000';
const origin = new URL(base).origin;
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Generated-library fixtures require loopback development only.');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const email = `generated-library-${crypto.randomUUID()}@example.test`;
const password = crypto.randomBytes(24).toString('hex');
const practiceId = 'xp.ielts-academic.00001';
const mockId = 'xm.ielts-academic.0001';
const canonical = JSON.parse(execFileSync(php, ['-r', 'require "includes/learning-catalog.php";echo json_encode(nisha_resolve_exam($argv[1]),JSON_THROW_ON_ERROR);', practiceId], { cwd: root, encoding: 'utf8' }));
const nextMock = JSON.parse(execFileSync(php, ['-r', 'require "includes/learning-catalog.php";$e=nisha_resolve_exam("xm.pte-core.0002");echo json_encode(["id"=>$e["id"],"version"=>$e["version"]],JSON_THROW_ON_ERROR);'], { cwd: root, encoding: 'utf8' }));
let browser, context, page, checks = 0;
const errors = [], external = [];
const ok = (value, label) => { assert(value, label); checks++; console.log('PASS', label); };
function hasAnswerKey(value) { return value && typeof value === 'object' && Object.entries(value).some(([key, child]) => ['answer', 'explanation', 'correct_text'].includes(key) || hasAnswerKey(child)); }
const submission = (exam, attempt = crypto.randomUUID()) => ({ exam_id: exam.id, exam_version: exam.version, attempt_id: attempt, answers: {}, elapsed_seconds: 0, completed_sections: [], assisted: true });
async function api(endpoint, payload) {
    return page.evaluate(async ({ endpoint, payload }) => {
        const response = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(payload) });
        return { status: response.status, data: await response.json() };
    }, { endpoint, payload });
}
async function configuration() { return page.locator('#exam-data').evaluate(node => JSON.parse(node.textContent)); }

(async () => {
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    context = await browser.newContext();
    await context.route('**/*', route => {
        const target = new URL(route.request().url());
        if (['http:', 'https:'].includes(target.protocol) && target.origin !== origin) { external.push(target.origin); return route.abort(); }
        return route.continue();
    });
    page = await context.newPage(); page.on('pageerror', error => errors.push(error.message));

    for (const course of ['ielts-academic', 'ielts-general', 'pte-academic', 'pte-core']) {
        for (const [file, prefix] of [['generated-practice.php', 'xp'], ['generated-mocks.php', 'xm']]) {
            const response = await page.goto(`${base}/${file}?course=${course}`);
            const links = await page.locator('.generated-card .btn').evaluateAll(nodes => nodes.map(node => new URL(node.href).searchParams.get('exam')));
            ok(response.status() === 200 && links.length === 24 && new Set(links).size === 24 && links.every(id => id.startsWith(`${prefix}.${course}.`)), `${course} ${prefix} catalogue provides a bounded page of distinct launch links`);
            ok(/not teacher reviewed/i.test(await page.locator('.generated-disclosure').innerText()) && await page.locator('.generated-card').filter({ hasText: 'Not teacher reviewed' }).count() === 24, `${course} ${prefix} labels generated content before a student launches it`);
        }
    }

    await page.goto(`${base}/generated-practice.php?course=ielts-academic&skill=writing`);
    ok(await page.locator('.generated-card .content-badge').evaluateAll(nodes => nodes.length === 24 && nodes.every(node => /Writing/.test(node.textContent))) && /1,500/.test(await page.locator('.generated-results-heading').innerText()), 'The writing filter exposes its actual matching variants');
    const firstPage = await page.locator('.generated-card .btn').evaluateAll(nodes => nodes.map(node => node.href));
    await page.locator('.content-pagination a').filter({ hasText: 'Next' }).click();
    const nextPage = await page.locator('.generated-card .btn').evaluateAll(nodes => nodes.map(node => node.href));
    ok(new URL(page.url()).searchParams.get('skill') === 'writing' && nextPage.length === 24 && nextPage.every(href => !firstPage.includes(href)), 'Pagination retains the skill filter and moves to different question variants');
    await page.goto(`${base}/generated-practice.php?course=pte-academic`);
    const selectedType = await page.locator('#generated-type option').evaluateAll(nodes => nodes.find(node => node.textContent === 'Re-order paragraphs')?.value);
    assert(selectedType, 'Expected supported PTE task type.');
    await page.locator('#generated-type').selectOption(selectedType);
    await Promise.all([page.waitForNavigation(), page.locator('.generated-filter button').click()]);
    ok(await page.locator('.generated-task-type').evaluateAll(nodes => nodes.length === 24 && nodes.every(node => node.textContent === 'Re-order paragraphs')), 'Question-type filtering selects the requested PTE interaction');
    await page.goto(`${base}/generated-mocks.php?course=pte-core&page=999999`);
    ok(await page.locator('.generated-card').count() === 6 && /Page 32 of 32/.test(await page.locator('.content-pagination').innerText()), 'An out-of-range catalogue page clamps to the real final mock page');

    for (const mode of ['light', 'dark']) {
        await page.evaluate(value => localStorage.setItem('nisha:theme', value), mode);
        for (const width of [320, 390, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            for (const file of ['generated-practice.php', 'generated-mocks.php']) {
                await page.goto(`${base}/${file}`);
                const fits = await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1);
                ok(fits && await page.locator('html').getAttribute('data-theme') === mode && await page.locator('.generated-disclosure').isVisible(), `${file} remains readable without horizontal overflow in ${mode} at ${width}px`);
            }
        }
    }

    await page.goto(`${base}/exams.php?exam=${practiceId}&start=1`);
    await page.locator('#exam-setup-dialog[open]').waitFor();
    let config = await configuration(), practice = config.exams[practiceId];
    ok(practice.session_type === 'practice' && practice.content_source === 'generated' && practice.teacher_reviewed === false && !hasAnswerKey(practice), 'Guest generated practice uses the canonical runner with no embedded answer key');
    ok(/not teacher-reviewed/i.test(await page.locator('#setup-format-note').innerText()), 'The runner setup preserves the unreviewed-content disclosure');
    await page.locator('#ready-confirmed').check(); await page.locator('#begin-exam').click();
    await page.locator('#exam-runner:not([hidden])').waitFor();
    const question = canonical.sections[0].items[0];
    await page.locator('.exam-choice').filter({ has: page.locator(`input[value="${question.answer}"]`) }).click();
    await page.locator('#check-practice-answer').click();
    await page.locator('#practice-feedback h3').waitFor();
    ok(/1\s*\/\s*1 practice points/i.test(await page.locator('#practice-feedback h3').innerText()), 'A guest can answer a generated question through the real interactive feedback flow');
    let reply = await api('api/practice-feedback.php', { exam_id: practice.id, exam_version: 'stale-version', item_id: question.id, response: question.answer });
    ok(reply.status === 409 && reply.data.code === 'exam_version_changed', 'Stale generated answers cannot be marked against changed templates');
    reply = await api('api/complete-exam.php', { ...submission(practice), answers: { [question.id]: question.answer } });
    ok(reply.status === 200 && !reply.data.saved && reply.data.result.objective_score === reply.data.result.objective_total && reply.data.result.content_source === 'generated' && reply.data.result.teacher_reviewed === false, 'Guest generated completion returns points and honest source metadata without consuming a mock');

    await page.goto(`${base}/exams.php?exam=${mockId}&start=1`); config = await configuration();
    const mock = config.exams[mockId];
    ok(mock.requires_access && Object.keys(mock.resources).length === 0 && mock.sections.every(section => !('items' in section)) && !hasAnswerKey(config), 'Generated full-mock HTML contains metadata only before access approval');
    reply = await api('api/start-mock.php', { exam_id: mockId, attempt_id: crypto.randomUUID() });
    ok(reply.status === 401 && !reply.data.exam, 'Guests cannot bypass sign-in to fetch a generated full paper');
    reply = await api('api/practice-feedback.php', { exam_id: mock.id, exam_version: mock.version, item_id: question.id, response: question.answer });
    ok(reply.status === 403 && !reply.data.review, 'A generated full paper cannot use the free single-question feedback endpoint');

    await page.goto(`${base}/register.php`);
    await page.locator('#name').fill('Generated Library Check'); await page.locator('#email').fill(email);
    await page.locator('#password').fill(password); await page.locator('#password_confirmation').fill(password);
    await Promise.all([page.waitForURL('**/dashboard.php'), page.locator('button[type=submit]').click()]);
    await page.goto(`${base}/exams.php?exam=${mockId}&start=1`);
    await page.locator('#exam-setup-dialog[open]').waitFor();
    await page.locator('#assisted-mode').check(); await page.locator('#ready-confirmed').check(); await page.locator('#begin-exam').click();
    await page.locator('#exam-runner:not([hidden])').waitFor();
    const started = await page.evaluate(id => {
        const c = JSON.parse(document.querySelector('#exam-data').textContent);
        const exam = c.exams[id], key = `nisha:fullmock:${c.userScope}:${id}:${exam.version}`;
        return { key, state: JSON.parse(localStorage.getItem(key)) };
    }, mockId);
    ok(started.state?.attemptId && /generated/i.test(await page.locator('#runner-exam-name').innerText()) && await page.locator('#runner-content-source').isVisible(), 'A registered student starts a real generated mock with a persistent source label');
    reply = await api('api/start-mock.php', { exam_id: mockId, attempt_id: started.state.attemptId });
    ok(reply.status === 200 && reply.data.grant.kind === 'trial' && reply.data.exam.sections.reduce((sum, section) => sum + section.items.length, 0) === 85 && !hasAnswerKey(reply.data.exam), 'The one free reservation resumes a sanitized generated full paper');
    reply = await api('api/start-mock.php', { exam_id: 'ielts-academic-01', attempt_id: crypto.randomUUID() });
    ok(reply.status === 402 && !reply.data.exam, 'The same trial reservation prevents a parallel starter full mock');
    await page.locator('#submit-exam-early').click(); await page.locator('#exam-confirm-dialog[open]').waitFor(); await page.locator('#exam-confirm-yes').click();
    await page.locator('#exam-result:not([hidden])').waitFor();
    const completed = await page.evaluate(key => JSON.parse(localStorage.getItem(key)).resultEnvelope, started.key);
    ok(completed.saved && completed.result_id && completed.mock_access.trial_state === 'consumed' && completed.result.content_source === 'generated' && completed.result.teacher_reviewed === false && /not teacher-reviewed/i.test(completed.result.format_note), 'Finishing atomically saves the generated-source result and consumes the free mock');
    reply = await api('api/start-mock.php', { exam_id: 'xm.pte-core.0002', attempt_id: crypto.randomUUID() });
    ok(reply.status === 402 && !reply.data.exam, 'Additional generated mocks share the normal paid-access boundary');
    reply = await api('api/complete-exam.php', submission(nextMock));
    ok(reply.status === 402 && !reply.data.result, 'A forged direct generated completion cannot reveal a second marked paper');
    await page.goto(`${base}/dashboard.php`);
    ok((await page.locator('main').innerText()).includes('Generated assembled mock 1') && await page.locator(`a[href="exams.php?result=${completed.result_id}"]`).first().isVisible(), 'Dashboard history identifies and links the saved generated mock');
    await page.goto(`${base}/exams.php?result=${completed.result_id}`);
    await page.locator('#exam-result:not([hidden])').waitFor();
    ok(await page.locator('#result-content-source').isVisible() && /not teacher-reviewed/i.test(await page.locator('#result-content-source').innerText()) && await page.locator('.exam-review-item').count() === 85, 'Saved generated results remain clearly labelled and readable after paid access is required');
    const saved = await context.request.get(`${base}/api/exam-result.php?id=${completed.result_id}`);
    ok(saved.status() === 200 && (await saved.json()).result.content_source === 'generated', 'Private result retrieval preserves the frozen generated-source metadata');
    ok(errors.length === 0 && external.length === 0, 'Generated catalogue and runner flows finish without JavaScript failures or paid/provider requests');
    console.log(`PASS: ${checks} generated-library browser/HTTP checks. No external provider calls.`);
})().catch(error => { console.error(error.stack); process.exitCode = 1; }).finally(async () => {
    if (context) await context.close(); if (browser) await browser.close();
    try { execFileSync(php, ['-r', 'require "includes/bootstrap.php";$q=db()->prepare("DELETE FROM users WHERE email=?");$q->execute([$argv[1]]);', email], { cwd: root, stdio: 'pipe' }); }
    catch (_) { console.error('Generated-library fixture cleanup failed.'); process.exitCode = 1; }
});
