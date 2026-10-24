/* Local paid-access boundary checks. No payment provider requests or real funds. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const base = process.env.NISHA_TEST_BASE || 'http://127.0.0.1:8000';
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Access fixtures require a loopback development server.');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const email = `mock-access-${crypto.randomUUID()}@example.test`;
const password = crypto.randomBytes(24).toString('hex');
const definitions = JSON.parse(execFileSync(php, ['-r', 'require "includes/learning-catalog.php"; $c=nisha_learning_catalog()[0];echo json_encode(["practice"=>nisha_resolve_exam($c["modules"][0]["practice_id"]),"section"=>nisha_resolve_exam($c["sections"][0]["exam_id"])]);'], { cwd: root, encoding: 'utf8' }));
let browser, context, page, checks = 0;
const errors = [], external = [];
function ok(value, label) { assert(value, label); checks++; console.log('PASS', label); }
function answerKeys(value) { return value && typeof value === 'object' && Object.entries(value).some(([key, child]) => ['answer', 'explanation', 'correct_text'].includes(key) || answerKeys(child)); }
function submission(exam, attempt = crypto.randomUUID()) { return { exam_id: exam.id, exam_version: exam.version, attempt_id: attempt, answers: {}, elapsed_seconds: 0, completed_sections: [], assisted: true }; }
async function api(endpoint, payload) {
    return page.evaluate(async ({ endpoint, payload }) => {
        const response = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(payload) });
        return { status: response.status, data: await response.json() };
    }, { endpoint, payload });
}
async function data() { return page.locator('#exam-data').evaluate(node => JSON.parse(node.textContent)); }
async function startSetup(id) {
    await page.goto(`${base}/exams.php?exam=${id}&start=1`);
    await page.locator('#exam-setup-dialog[open]').waitFor();
    await page.locator('#assisted-mode').check(); await page.locator('#ready-confirmed').check();
    await page.locator('#begin-exam').click();
}
(async () => {
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    context = await browser.newContext();
    await context.route('**/*', route => {
        const target = new URL(route.request().url());
        if (['http:', 'https:'].includes(target.protocol) && target.origin !== new URL(base).origin) { external.push(target.origin); return route.abort(); }
        return route.continue();
    });
    page = await context.newPage(); page.on('pageerror', e => errors.push(e.message));
    await page.goto(`${base}/exams.php?exam=ielts-academic-01&start=1`);
    let publicData = await data(); const metadata = publicData.exams['ielts-academic-01'];
    ok(Object.values(publicData.exams).every(exam => exam.session_type !== 'full' || (Object.keys(exam.resources).length === 0 && exam.sections.every(section => !('items' in section)))), 'Guest full-mock HTML contains metadata only, without questions or stimuli');
    ok(!answerKeys(publicData), 'Guest public payload contains no answer keys');
    ok(await page.locator('#exam-catalog-message a[href="login.php"]').isVisible() && !await page.locator('#exam-setup-dialog').isVisible(), 'Guest launch explains account sign-in before opening a free paper');
    let reply = await api('api/start-mock.php', { exam_id: metadata.id, attempt_id: crypto.randomUUID() });
    ok(reply.status === 401 && !reply.data.exam, 'Unauthenticated direct start cannot release a paper');
    reply = await api('api/complete-exam.php', submission(metadata));
    ok(reply.status === 401 && !reply.data.result, 'Unauthenticated direct full submission cannot reveal a marked paper');
    for (const [kind, exam] of Object.entries(definitions)) {
        reply = await api('api/complete-exam.php', submission(exam));
        ok(reply.status === 200 && reply.data.result.exam_id === exam.id && !reply.data.saved, `${kind} feedback remains available to guests and does not consume a full mock`);
    }
    await page.goto(`${base}/register.php`);
    await page.locator('#name').fill('Mock Access Check'); await page.locator('#email').fill(email);
    await page.locator('#password').fill(password); await page.locator('#password_confirmation').fill(password);
    await page.locator('button[type="submit"]').click(); await page.waitForURL('**/dashboard.php');
    await startSetup('ielts-academic-01'); await page.locator('#exam-runner:not([hidden])').waitFor();
    const first = await page.evaluate(() => {
        const config = JSON.parse(document.querySelector('#exam-data').textContent);
        const exam = config.exams['ielts-academic-01']; const key = `nisha:fullmock:${config.userScope}:${exam.id}:${exam.version}`;
        return { key, state: JSON.parse(localStorage.getItem(key)) };
    });
    ok(first.state && first.state.attemptId && await page.locator('.exam-short-input').isVisible(), 'A student opens one genuine free full mock through the start endpoint');
    await page.locator('.exam-short-input').fill('Retained answer');
    reply = await api('api/start-mock.php', { exam_id: metadata.id, attempt_id: first.state.attemptId });
    ok(reply.status === 200 && reply.data.grant.kind === 'trial' && reply.data.exam.sections[0].items.length === 40 && !answerKeys(reply.data.exam), 'Same reservation resumes the same sanitized free paper');
    reply = await api('api/start-mock.php', { exam_id: 'pte-academic-01', attempt_id: crypto.randomUUID() });
    ok(reply.status === 402 && !reply.data.exam, 'A parallel second full mock is blocked while the free mock is reserved');
    reply = await api('api/start-mock.php', { exam_id: 'pte-academic-01', attempt_id: first.state.attemptId });
    ok(reply.status === 409 && !reply.data.exam, 'An attempt reference cannot be reassigned to another paper');
    const wrong = submission({ id: 'pte-academic-01', version: publicData.exams['pte-academic-01'].version });
    reply = await api('api/complete-exam.php', wrong);
    ok(reply.status === 402 && !reply.data.result, 'Tampered direct completion cannot grade an unreserved second full mock');
    await page.waitForTimeout(300); await page.reload();
    await page.locator('.exam-short-input').waitFor();
    const resumed = await page.evaluate(key => JSON.parse(localStorage.getItem(key)), first.key);
    ok(await page.locator('.exam-short-input').inputValue() === 'Retained answer' && resumed.attemptId === first.state.attemptId && resumed.sectionDeadline === first.state.sectionDeadline, 'Reload resumes the server reservation without changing answers, attempt or deadline');
    await page.locator('#submit-exam-early').click(); await page.locator('#exam-confirm-dialog[open]').waitFor(); await page.locator('#exam-confirm-yes').click();
    await page.locator('#exam-result:not([hidden])').waitFor();
    const completed = await page.evaluate(key => JSON.parse(localStorage.getItem(key)), first.key);
    const envelope = completed.resultEnvelope;
    ok(envelope.saved && envelope.result_id && envelope.mock_access.trial_state === 'consumed', 'Finishing saves the result and consumes the free mock together');
    reply = await api('api/complete-exam.php', { ...submission(metadata, first.state.attemptId), answers: {}, elapsed_seconds: 999 });
    ok(reply.status === 200 && reply.data.result_id === envelope.result_id && JSON.stringify(reply.data.result) === JSON.stringify(envelope.result), 'A repeated completion returns the immutable original result without another trial consumption');
    await page.goto(`${base}/pricing.php?checkout=success`);
    reply = await api('api/start-mock.php', { exam_id: 'pte-academic-01', attempt_id: crypto.randomUUID() });
    ok(reply.status === 402 && reply.data.code === 'mock_payment_required' && !reply.data.exam, 'A forged checkout success URL cannot create paid access');
    await startSetup('pte-academic-01');
    await page.locator('#setup-access-message a[href="pricing.php"]').waitFor();
    ok(!await page.locator('#exam-runner').isVisible(), 'The next browser mock shows pricing without revealing a second paper');
    await page.goto(`${base}/exams.php?result=${envelope.result_id}`); await page.locator('#exam-result:not([hidden])').waitFor();
    ok(await page.locator('.exam-review-item').count() === 85, 'An expired or unpaid account can still reopen its own full answer review');
    execFileSync(php, ['-r', 'require "includes/bootstrap.php";$q=db()->prepare("SELECT id FROM users WHERE email=?");$q->execute([$argv[1]]);$id=(int)$q->fetchColumn();foreach(["commerce_trials","mock_access_grants"]as$table){$q=db()->prepare("DELETE FROM ".$table." WHERE user_id=?");$q->execute([$id]);}', email], { cwd: root, stdio: 'pipe' });
    reply = await api('api/start-mock.php', { exam_id: 'pte-academic-01', attempt_id: crypto.randomUUID() });
    ok(reply.status === 402 && reply.data.code === 'mock_payment_required', 'A full result saved before commerce setup still counts as the one free mock');
    const resultResponse = await context.request.get(`${base}/api/exam-result.php?id=${envelope.result_id}`);
    ok(resultResponse.status() === 200 && (await resultResponse.json()).result.attempt_id === first.state.attemptId, 'Legacy result review remains available without a new payment or access grant');
    ok(errors.length === 0 && external.length === 0, 'Access checks finish without browser errors or payment-provider calls');
    console.log(`PASS: ${checks} mock-access browser/HTTP checks. No payment provider calls.`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (context) await context.close(); if (browser) await browser.close();
    execFileSync(php, ['-r', 'require "includes/bootstrap.php";$q=db()->prepare("DELETE FROM users WHERE email=?");$q->execute([$argv[1]]);', email], { cwd: root, stdio: 'pipe' });
});
