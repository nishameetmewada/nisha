/* Local HTTP + browser integration. No API key and no paid provider requests. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync, spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const artifacts = fs.mkdtempSync('/tmp/nisha-ai-browser-');
const config = JSON.parse(execFileSync(php, ['-r', `require 'includes/ai-review-service.php'; $sessions=[]; foreach(nisha_learning_catalog() as $course) foreach($course['modules'] as $module) { $exam=nisha_resolve_exam($module['practice_id']); foreach(nisha_exam_items($exam) as $item) if(in_array($item['type'],['essay','spoken'],true) && !isset($sessions[$item['type']])) $sessions[$item['type']]=['exam'=>$exam,'item'=>$item]; } echo json_encode(['configured'=>nisha_ai_configured(),'sessions'=>$sessions]);`], { cwd: root, encoding: 'utf8' }));
const pteWriting = JSON.parse(execFileSync(php, ['-r', `require 'includes/ai-review-service.php'; echo json_encode(nisha_resolve_exam('practice.pte-academic.writing-summarize-written-text'));`], { cwd: root, encoding: 'utf8' }));
const fullAcademic = JSON.parse(execFileSync(php, ['-r', `require 'includes/learning-catalog.php'; echo json_encode(nisha_resolve_exam('ielts-academic-01'));`], { cwd: root, encoding: 'utf8' }));
assert.equal(config.configured, false, 'This no-key HTTP test refuses to run when paid AI is configured.');
let server, browser, context, page, email;
let base, checks = 0;
const errors = [];
const external = [];
function ok(value, label) { assert(value, label); ++checks; console.log('PASS', label); }
const responseText = 'Reliable public transport makes daily travel easier. Frequent buses help students reach their classes on time.';
const request = (exam, item, response = responseText) => ({ request_id: crypto.randomUUID(), exam_id: exam.id, exam_version: exam.version, item_id: item.id, attempt_id: crypto.randomUUID(), response });
const api = (payload, csrf = true, contentType = 'application/json') => page.evaluate(async ({ payload, csrf, contentType }) => {
    const headers = { 'Content-Type': contentType };
    if (csrf) headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]').content;
    const result = await fetch('api/ai-review.php', { method: 'POST', headers, body: typeof payload === 'string' ? payload : JSON.stringify(payload) });
    return { status: result.status, body: await result.json() };
}, { payload, csrf, contentType });
async function start(id) {
    await page.goto(`${base}/exams.php?exam=${encodeURIComponent(id)}&start=1`);
    if (await page.locator('#exam-runner').isVisible()) return;
    if (await page.locator('#exam-result').isVisible()) {
        await page.locator('#start-new-attempt').click();
        await page.locator('#exam-confirm-dialog[open]').waitFor();
        await page.locator('#exam-confirm-yes').click();
    }
    await page.locator('#exam-setup-dialog').waitFor({ state: 'visible' });
    await page.locator('#assisted-mode').check(); await page.locator('#ready-confirmed').check();
    await page.locator('#begin-exam').click();
    await page.locator('#exam-runner:not([hidden])').waitFor();
}
async function finishCurrentPractice() {
    await page.locator('#submit-exam-early').click();
    await page.locator('#exam-confirm-dialog[open]').waitFor();
    await page.locator('#exam-confirm-yes').click();
    await page.locator('#exam-result:not([hidden])').waitFor();
}
async function localResult(id) {
    return page.evaluate(examId => {
        const data = JSON.parse(document.querySelector('#exam-data').textContent);
        const exam = data.exams[examId];
        const key = `nisha:fullmock:${data.userScope}:${exam.id}:${exam.version}`;
        return { key, state: JSON.parse(localStorage.getItem(key)) };
    }, id);
}
async function restoreLegacyResult(id) {
    const saved = await localResult(id);
    assert.equal(saved.state.phase, 'results', 'The old-copy regression needs a real completed result');
    // An already deployed device result may retain this old server note even
    // after application assets have been updated. Replace only that old note;
    // retain the real result, answers, attempt ID and normal restore path.
    saved.state.resultEnvelope.result.grading_note = 'Objective raw practice points only. Writing and speaking require self-review and have no automatic score.';
    await page.addInitScript(({ key, state, marker }) => {
        if (sessionStorage.getItem(marker)) return;
        localStorage.setItem(key, JSON.stringify(state));
        sessionStorage.setItem(marker, 'used');
    }, { ...saved, marker: crypto.randomUUID() });
    await page.reload();
    await page.locator('#exam-result:not([hidden])').waitFor();
    return saved;
}
function reviewEntry(payload, transcript) {
    const spoken = typeof transcript === 'string';
    const pte = payload.exam_id.includes('pte-');
    return { id: ++reviewEntry.id, exam_id: payload.exam_id, exam_version: payload.exam_version, item_id: payload.item_id, attempt_id: payload.attempt_id, response: payload.response,
        review: { mode: spoken ? 'speaking-transcript' : 'writing', score: { label: spoken ? 'Speaking content and language review' : pte ? 'PTE Writing practice review' : 'IELTS Writing practice band estimate', value: spoken ? 70 : pte ? 65 : 6.5, max: spoken || pte ? 100 : 9, unit: spoken || pte ? '%' : 'band' },
            criteria: [{ label: 'Task response', score: spoken ? 70 : pte ? 65 : 6.5, max: spoken || pte ? 100 : 9, feedback: 'Develop one specific example.' }],
            summary: '<img src=x onerror="window.__aiXss=1"> A clear central idea.', strengths: ['Relevant main idea.'], improvements: [{ issue: 'Develop the example.', evidence: 'public transport', suggestion: 'Explain how this benefits students.', example: 'A frequent bus route reduces waiting time.' }],
            next_steps: ['Add one specific example.', 'Check sentence boundaries.', 'Review the word range.'], improved_sample: '<script>window.__aiXss=2</script> Learning example: Public transport helps students arrive on time.',
            limitations: ['AI-generated practice estimate; not an official result.', ...(spoken ? ['Pronunciation and oral fluency were not assessed.'] : [])], transcript: spoken ? transcript : null, word_count: 18, model: 'deterministic-browser-fixture', reviewed_at: new Date().toISOString() } };
}
reviewEntry.id = 1000;
const uiAvailability = { configured: true, transportAvailable: true, studentDailyLimit: 10, maxAudioBytes: 10 * 1024 * 1024 };
async function enableUiFixture(targetContext = context) {
    // Only the browser copy of public availability is changed. The actual HTTP
    // endpoint remains unconfigured; subsequent UI calls are explicitly intercepted.
    await targetContext.route('**/exams.php?*', async route => {
        const response = await route.fetch();
        let body = await response.text();
        body = body.replace(/(<script id="exam-data" type="application\/json">)([\s\S]*?)(<\/script>)/, (_, open, json, close) => {
            const value = JSON.parse(json); value.aiReview = { ...uiAvailability };
            return open + JSON.stringify(value).replace(/</g, '\\u003c') + close;
        });
        await route.fulfill({ response, body });
    });
}
function wav(seconds = 1) {
    const size = 16000 * 2 * seconds;
    const buffer = Buffer.alloc(44 + size);
    buffer.write('RIFF', 0); buffer.writeUInt32LE(36 + size, 4); buffer.write('WAVEfmt ', 8); buffer.writeUInt32LE(16, 16); buffer.writeUInt16LE(1, 20); buffer.writeUInt16LE(1, 22); buffer.writeUInt32LE(16000, 24); buffer.writeUInt32LE(32000, 28); buffer.writeUInt16LE(2, 32); buffer.writeUInt16LE(16, 34); buffer.write('data', 36); buffer.writeUInt32LE(size, 40);
    for (let sample = 0; sample < size / 2; ++sample) buffer.writeInt16LE(Math.round(Math.sin(sample * 2 * Math.PI * 220 / 16000) * 1000), 44 + sample * 2);
    return buffer;
}
async function upload(buffer, mime = 'audio/wav') {
    const { exam, item } = config.sessions.spoken;
    const fields = request(exam, item, 'Local reflection notes.');
    return page.evaluate(async ({ fields, encoded, mime }) => {
        const form = new FormData(); for (const [key, value] of Object.entries(fields)) form.append(key, value);
        const bytes = Uint8Array.from(atob(encoded), char => char.charCodeAt(0));
        form.append('audio', new Blob([bytes], { type: mime }), 'response.wav');
        const result = await fetch('api/ai-review.php', { method: 'POST', headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }, body: form });
        return { status: result.status, body: await result.json() };
    }, { fields, encoded: buffer.toString('base64'), mime });
}

(async () => {
    const port = await new Promise((resolve, reject) => { const listener = net.createServer(); listener.once('error', reject); listener.listen(0, '127.0.0.1', () => { const value = listener.address().port; listener.close(() => resolve(value)); }); });
    base = `http://127.0.0.1:${port}`;
    server = spawn(php, ['-d', `upload_tmp_dir=${artifacts}`, '-d', 'upload_max_filesize=12M', '-d', 'post_max_size=16M', '-S', `127.0.0.1:${port}`, '-t', root], { cwd: root, stdio: ['ignore', 'ignore', 'pipe'] });
    let serverErrors = ''; server.stderr.on('data', value => { serverErrors += value.toString(); });
    for (let count = 0; count < 40; ++count) { try { await fetch(base); break; } catch (_) { await new Promise(resolve => setTimeout(resolve, 100)); if (count === 39) throw new Error('Local PHP test server failed to start.'); } }
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    context = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
    await context.route('**/*', route => { const url = new URL(route.request().url()); if (['http:', 'https:'].includes(url.protocol) && url.origin !== base) { external.push(url.origin); return route.abort(); } return route.continue(); });
    page = await context.newPage(); page.on('pageerror', error => errors.push(error.message));
    const guidedWarmUps = [
        ['IELTS', 'Writing', 'practice.ielts-academic.writing-academic-writing-task-2'],
        ['IELTS', 'Speaking', 'practice.ielts-academic.speaking-speaking-part-2-indiv-2b10de49'],
        ['PTE', 'Writing', 'practice.pte-academic.writing-write-essay'],
        ['PTE', 'Speaking', 'practice.pte-academic.speaking-read-aloud'],
    ];
    for (const [examName, skill, target] of guidedWarmUps) {
        await page.goto(`${base}/quick-practice.php?exam=${examName}&skill=${skill}`);
        assert(await page.locator('#guided-practice-notice').isVisible(), 'The warm-up explains that it opens guided practice.');
        await page.locator('#start-practice').click();
        await page.waitForURL(url => url.pathname === '/exams.php' && url.searchParams.get('exam') === target);
        await page.locator('#ready-confirmed').check(); await page.locator('#begin-exam').click();
        ok(await page.locator('#item-response .ai-review-panel').isVisible() && await page.locator('#item-response [data-ai-action]').isDisabled(), `${examName} ${skill} warm-up opens its matching guided task and honest guest AI coaching state`);
        const direct = await page.request.get(`${base}/quick-practice.php?exam=${examName}&skill=${skill}&start=1`, { maxRedirects: 0 });
        ok(direct.status() === 302 && new URL(direct.headers().location, base).searchParams.get('exam') === target, `Old ${examName} ${skill} direct links use the same AI-capable guided session`);
    }
    for (const skill of ['Reading', 'Listening']) {
        await page.goto(`${base}/quick-practice.php?exam=PTE&skill=${skill}&start=1`);
        ok(await page.locator('#practice-session').isVisible() && await page.locator('#question-options input').count() > 0 && new URL(page.url()).pathname === '/quick-practice.php', `${skill} short warm-ups still open their original interactive questions`);
    }
    const { exam, item } = config.sessions.essay;
    await start(exam.id);
    const assetUrls = await page.locator('script[src],link[rel="stylesheet"]').evaluateAll(elements => elements.map(element => element.src || element.href));
    ok(assetUrls.some(value => new URL(value).pathname.endsWith('/assets/js/exams.js')) && assetUrls.some(value => new URL(value).pathname.endsWith('/assets/js/ai-review.js')) && assetUrls.every(value => {
        const url = new URL(value);
        const localFile = path.join(root, url.pathname);
        return url.origin === base && url.searchParams.get('v') === crypto.createHash('sha256').update(fs.readFileSync(localFile)).digest('hex').slice(0, 16);
    }), 'Rendered exam scripts and styles use their actual content version so an older browser cache gets the changed files');
    await page.locator('.exam-writing-area').fill(responseText);
    const guestPanel = page.locator('.ai-review-panel');
    await guestPanel.waitFor();
    ok(await guestPanel.locator('[data-ai-action]').isDisabled() && (await guestPanel.innerText()).includes('Sign in'), 'Guests see an honest sign-in requirement and cannot request paid AI');
    await finishCurrentPractice();
    ok(await page.locator('#result-ai-status').getAttribute('data-ai-state') === 'setup' && await page.locator('#result-ai-status').isVisible() && await page.locator('#result-ai-sign-in').isVisible() && /sign in/i.test(await page.locator('#result-ai-message').innerText()), 'Unconfigured guest results explain API setup and the sign-in requirement above the answer details');
    ok((await page.locator('#exam-result-points').innerText()).trim() !== '0/0' && (await page.locator('.exam-review-points').first().innerText()).includes('AI setup needed'), 'Productive results show the actionable AI state instead of zero points or a primary self-review label');
    const guestStored = await restoreLegacyResult(exam.id);
    const guestRestored = await localResult(exam.id);
    ok(guestRestored.state.attemptId === guestStored.state.attemptId && guestRestored.state.answers[item.id] === responseText && await page.locator('#result-ai-status').getAttribute('data-ai-state') === 'setup', 'An existing device result reopens with the current AI status without clearing its answer or attempt');
    ok(!(await page.locator('#exam-grading-note').innerText()).includes('require self-review and have no automatic score') && guestRestored.state.resultEnvelope.result.grading_note === guestStored.state.resultEnvelope.result.grading_note, 'A legacy server note cannot override current AI guidance and its original downloaded data remains intact');
    await page.locator('#result-ai-action').click();
    ok(await page.locator(`.exam-review-item[data-review-item="${item.id}"]`).getAttribute('open') !== null && await page.locator('#exam-result .ai-review-panel').isVisible() && await page.locator('#exam-result [data-ai-action]').isDisabled(), 'The guest result shortcut opens its real AI panel and sign-in explanation without requesting a review');
    const guest = await api(request(exam, item));
    ok(guest.status === 401 && guest.body.code === 'ai_login_required', 'HTTP rejects guest AI requests before provider processing');
    const method = await page.request.put(`${base}/api/ai-review.php`);
    ok(method.status() === 405 && method.headers().allow === 'GET, POST', 'AI endpoint accepts only review POSTs and read-only status GETs');
    const guestStatus = await page.request.get(`${base}/api/ai-review.php?request_id=${crypto.randomUUID()}`);
    ok(guestStatus.status() === 401, 'Reading a review status requires the owner’s authenticated session');
    email = `ai-browser-${crypto.randomBytes(10).toString('hex')}@example.test`;
    const password = crypto.randomBytes(24).toString('hex');
    await page.goto(`${base}/register.php`);
    await page.locator('#name').fill('AI browser fixture'); await page.locator('#email').fill(email); await page.locator('#password').fill(password); await page.locator('#password_confirmation').fill(password);
    await page.locator('button[type="submit"]').click(); await page.waitForURL('**/dashboard.php');
    const badStatus = await page.request.get(`${base}/api/ai-review.php?request_id=invalid`);
    ok(badStatus.status() === 422, 'Authenticated review-status reads validate the request reference');
    const unknownStatus = await page.request.get(`${base}/api/ai-review.php?request_id=${crypto.randomUUID()}`);
    ok(unknownStatus.status() === 404 && (await unknownStatus.json()).state === 'not_found', 'An unknown owner review returns a read-only not-found status without requiring an API key');
    const csrf = await api(request(exam, item), false);
    ok(csrf.status === 403 && csrf.body.code === 'csrf_expired', 'Signed-in HTTP requests require the session CSRF token');
    const malformed = await api('{');
    ok(malformed.status === 400, 'Malformed JSON is rejected without a fabricated result');
    const list = await api([]);
    ok(list.status === 422, 'AI endpoint rejects a JSON list in place of a request object');
    const media = await api('response', true, 'text/plain');
    ok(media.status === 415, 'AI endpoint rejects unsupported request content types');
    const oversized = await api('x'.repeat(100001));
    ok(oversized.status === 413, 'Oversized JSON input is bounded before service processing');
    const invalid = request(exam, item); invalid.item_id = 'not-a-task';
    const invalidTask = await api(invalid);
    ok(invalidTask.status === 422 && invalidTask.body.code === 'ai_invalid_task', 'HTTP validates the canonical task rather than trusting client labels: ' + JSON.stringify(invalidTask));
    const unconfigured = await api(request(exam, item));
    ok(unconfigured.status === 503 && unconfigured.body.code === 'ai_not_configured' && !('review' in unconfigured.body), 'Missing API setup returns a clear unavailable state and no invented score');
    await start(exam.id); await page.locator('.exam-writing-area').fill(responseText);
    ok(await page.locator('#item-response [data-ai-action]').isDisabled() && (await page.locator('#item-response [data-ai-availability]').innerText()).includes('awaiting'), 'Signed-in learners see that AI still needs the owner’s API setup');
    await finishCurrentPractice();
    ok(await page.locator('#result-ai-status').getAttribute('data-ai-state') === 'setup' && /OpenAI|API setup/i.test(await page.locator('#result-ai-message').innerText()) && (await page.locator('.exam-review-points').first().innerText()).includes('AI setup needed'), 'Saved signed-in results identify missing API setup before the learner opens any answer');
    await page.locator('#result-ai-action').click();
    ok(await page.locator('#exam-result .ai-review-panel').isVisible() && await page.locator('#exam-result [data-ai-action]').isDisabled(), 'The setup-needed result shortcut exposes the disabled AI panel and retains the written response');
    const validUpload = await upload(wav());
    ok(validUpload.status === 503 && validUpload.body.code === 'ai_not_configured', 'A valid local WAV reaches configuration validation without any provider request');
    const maximumUpload = await upload(wav(300));
    ok(maximumUpload.status === 503 && maximumUpload.body.code === 'ai_not_configured', 'A real five-minute 9.6 MB PCM WAV passes PHP upload and duration bounds without calling OpenAI');
    const tooLarge = await api('x'.repeat(11 * 1024 * 1024 + 1));
    ok(tooLarge.status === 413 && !('review' in tooLarge.body), 'Requests larger than the endpoint body limit fail without a score');
    const invalidUpload = await upload(Buffer.from('<?php ' + 'x'.repeat(200)));
    ok(invalidUpload.status === 422 && invalidUpload.body.code === 'ai_invalid_audio', 'Spoofed audio is rejected using actual file contents');
    ok(fs.readdirSync(artifacts).length === 0, 'Accepted and rejected multipart requests leave no temporary upload files');
    const count = Number(execFileSync(php, ['-r', `require 'includes/bootstrap.php'; $s=db()->prepare('SELECT COUNT(*) FROM ai_reviews r JOIN users u ON u.id=r.user_id WHERE u.email=?'); $s->execute([$argv[1]]); echo $s->fetchColumn();`, email], { cwd: root, encoding: 'utf8' }));
    ok(count === 0, 'Authentication, validation and missing-setup failures do not reserve quota or persist fake reviews');

    await enableUiFixture();
    const sent = [];
    const audioSent = [];
    const statusRequests = [];
    const statusReplies = new Map();
    let responseScenario = null;
    let failFirst = true;
    let holdResolve;
    let holdResponse = false;
    await context.route('**/api/ai-review.php*', async route => {
        if (route.request().method() === 'GET') {
            const id = new URL(route.request().url()).searchParams.get('request_id');
            statusRequests.push(id);
            const reply = statusReplies.get(id) || { state: 'not_found' };
            if (reply.state === 'unavailable') return route.abort('connectionreset');
            return route.fulfill({ status: reply.state === 'not_found' ? 404 : reply.state === 'pending' ? 202 : 200, contentType: 'application/json', body: JSON.stringify({ success: reply.state !== 'not_found', ...reply }) });
        }
        assert.equal(route.request().method(), 'POST');
        let payload;
        let uploadedAudio = null;
        if (route.request().headers()['content-type']?.startsWith('multipart/form-data')) {
            const form = await new Response(route.request().postDataBuffer(), { headers: { 'Content-Type': route.request().headers()['content-type'] } }).formData();
            payload = Object.fromEntries([...form.entries()].filter(([key]) => key !== 'audio'));
            uploadedAudio = form.get('audio');
            const bytes = Buffer.from(await uploadedAudio.arrayBuffer());
            audioSent.push({ bytes, filename: uploadedAudio.name, mime: uploadedAudio.type });
        } else payload = route.request().postDataJSON();
        sent.push(payload);
        assert(route.request().headers()['x-csrf-token'], 'UI sends its session CSRF token');
        if (failFirst) { failFirst = false; return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ error: 'Fixture provider temporarily unavailable. Your response is retained.' }) }); }
        if (holdResponse) await new Promise(resolve => { holdResolve = resolve; });
        const entry = reviewEntry(payload, uploadedAudio ? 'Reliable public transport helps me reach my classes on time.' : payload.transcript);
        if (uploadedAudio) entry.review.mode = 'speaking-audio';
        if (responseScenario) {
            const scenario = responseScenario; responseScenario = null;
            if (scenario === 'html413') return route.fulfill({ status: 413, contentType: 'text/html', body: '<h1>Request Entity Too Large</h1>' });
            if (scenario === 'html504') return route.fulfill({ status: 504, contentType: 'text/html', body: '<h1>Gateway Timeout</h1>' });
            statusReplies.set(payload.request_id, scenario === 'drop-completed' ? { state: 'completed', entry } : ['drop-pending', 'in-progress'].includes(scenario) ? { state: 'pending', entry } : { state: 'not_found' });
            if (scenario === 'in-progress') return route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ error: 'This review is already processing.', code: 'ai_in_progress' }) });
            return route.abort('connectionreset');
        }
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, saved: true, cached: false, entry, usage: { remaining_today: 9, limit: 10, resets: '00:00 UTC' } }) });
    });
    await start(exam.id); await page.locator('.exam-writing-area').fill(responseText);
    await page.locator('#item-response [data-ai-action]').click();
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'temporarily unavailable' }).waitFor();
    ok(await page.locator('.exam-writing-area').inputValue() === responseText && await page.locator('#item-response [data-ai-feedback]').isHidden(), 'A provider failure keeps the written answer and shows no score');
    await page.locator('#item-response [data-ai-action]').click(); await page.locator('#item-response [data-ai-feedback]:not([hidden])').waitFor();
    ok(sent.length === 2 && sent[0].request_id === sent[1].request_id, 'Retry reuses the same review reference for unchanged work');
    ok((await page.locator('#item-response [data-ai-feedback]').innerText()).includes('6.5 / 9') && await page.locator('#item-response .ai-review-criterion').count() === 1 && (await page.locator('#item-response [data-ai-feedback]').innerText()).includes('Your next practice steps'), 'AI feedback renders its estimate, rubric and actionable improvement plan');
    ok(await page.locator('#item-response [data-ai-feedback] img, #item-response [data-ai-feedback] script').count() === 0 && await page.evaluate(() => window.__aiXss) === undefined, 'Model feedback and learning samples render as safe text');
    const changed = responseText + ' Students can therefore plan their mornings more reliably.';
    await page.locator('.exam-writing-area').fill(changed);
    ok(await page.locator('#item-response [data-ai-feedback]').isHidden(), 'Editing the written response hides feedback for the earlier answer');
    holdResponse = true; await page.locator('#item-response [data-ai-action]').click();
    await page.waitForFunction(() => document.querySelector('#item-response .ai-review-panel').getAttribute('aria-busy') === 'true');
    for (let count = 0; !holdResolve && count < 100; ++count) await new Promise(resolve => setTimeout(resolve, 20));
    assert(holdResolve, 'The held request must reach the route');
    await page.locator('.exam-writing-area').fill(changed + ' A further edit during review.');
    holdResolve(); holdResponse = false;
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'changed while' }).waitFor();
    ok(await page.locator('#item-response [data-ai-feedback]').isHidden() && sent[2].request_id !== sent[1].request_id, 'Late feedback stays attached to its original answer and changed work gets a new request reference');
    await finishCurrentPractice();
    ok(await page.locator('#result-ai-status').getAttribute('data-ai-state') === 'ready' && (await page.locator('.exam-review-points').first().innerText()).includes('AI review available'), 'A configured saved result offers AI review for its unchanged, unreviewed writing response');
    const callsBeforeResultJump = sent.length;
    await page.locator('#result-ai-action').click();
    ok(await page.locator(`.exam-review-item[data-review-item="${item.id}"]`).getAttribute('open') !== null && await page.locator('#exam-result [data-ai-action]').isVisible() && sent.length === callsBeforeResultJump, 'The result action opens the first productive answer without automatically sending it to a paid API');
    holdResponse = true; holdResolve = null;
    await page.locator('#exam-result [data-ai-action]').click();
    await page.waitForFunction(() => document.querySelector('#result-ai-status').dataset.aiState === 'reviewing');
    for (let count = 0; !holdResolve && count < 100; ++count) await new Promise(resolve => setTimeout(resolve, 20));
    assert(holdResolve, 'The result review must reach its explicit browser fixture');
    ok((await page.locator('.exam-review-points').first().innerText()).includes('Reviewing') && (await page.locator('#exam-result-sections').innerText()).includes('Reviewing'), 'A requested review updates the result summary, section and task to a pending state');
    holdResolve(); holdResponse = false;
    await page.waitForFunction(() => document.querySelector('#result-ai-status').dataset.aiState === 'reviewed');
    ok(/AI\s+6\.5\s*\/\s*9/.test(await page.locator('.exam-review-points').first().innerText()) && /AI reviewed|AI feedback ready/i.test(await page.locator('#exam-result-sections').innerText()), 'Successful coaching updates the task estimate and section status without blending it into objective points');
    const reviewedResult = await localResult(exam.id);
    ok(reviewedResult.state.resultEnvelope.result.objective_total === 0 && reviewedResult.state.resultEnvelope.result.objective_score === 0 && reviewedResult.state.aiReviews.length > 0, 'AI coaching persists alongside the result while objective raw points remain unchanged');
    const callsBeforeResultRestore = sent.length;
    await page.reload(); await page.locator('#exam-result:not([hidden])').waitFor();
    ok(await page.locator('#result-ai-status').getAttribute('data-ai-state') === 'reviewed' && /AI\s+6\.5\s*\/\s*9/.test(await page.locator('.exam-review-points').first().innerText()) && sent.length === callsBeforeResultRestore, 'A completed AI result restores its saved estimate without another provider call');
    await start(pteWriting.id);
    const pteItems = pteWriting.sections.flatMap(section => section.items);
    assert.equal(pteItems.length, 2, 'The screenshot regression requires both real PTE written-summary tasks');
    await page.locator('.exam-writing-area').fill(responseText);
    await page.locator('#next-item').click();
    await page.locator('.exam-writing-area').fill(responseText + ' This is a separate answer to the second task.');
    await finishCurrentPractice();
    await page.locator('#result-ai-action').click();
    const firstPteRow = page.locator(`.exam-review-item[data-review-item="${pteItems[0].id}"]`);
    const secondPteRow = page.locator(`.exam-review-item[data-review-item="${pteItems[1].id}"]`);
    await firstPteRow.locator('[data-ai-action]').click();
    await page.waitForFunction(() => document.querySelector('#result-ai-status').dataset.aiState === 'partial');
    ok(/AI\s+65%/.test(await firstPteRow.locator('.exam-review-points').innerText()) && (await secondPteRow.locator('.exam-review-points').innerText()).includes('AI review available'), 'Two-task PTE results show each task’s own practice percentage and keep the remaining answer available for review');
    const callsBeforeNextAnswer = sent.length;
    await page.locator('#result-ai-action').click();
    ok(await secondPteRow.getAttribute('open') !== null && await secondPteRow.locator('[data-ai-action]').isVisible() && sent.length === callsBeforeNextAnswer, 'The result shortcut advances to the first pending answer rather than repeating a reviewed task');
    await secondPteRow.locator('[data-ai-action]').click();
    await page.waitForFunction(() => document.querySelector('#result-ai-status').dataset.aiState === 'reviewed');
    ok(/AI\s+65%/.test(await secondPteRow.locator('.exam-review-points').innerText()) && (await localResult(pteWriting.id)).state.resultEnvelope.result.objective_total === 0, 'Both PTE written-summary tasks receive separate coaching percentages while no objective or official score is invented');
    await start(config.sessions.spoken.exam.id);
    await page.locator('.exam-speaking-notes').fill('I want to speak more clearly next time.');
    ok(await page.locator('#item-response [data-ai-action]').isDisabled(), 'Speaking reflections alone cannot trigger an AI language review');
    await page.locator('#item-response [data-ai-transcript]').fill('Reliable public transport helps me reach my classes on time.');
    await page.locator('#item-response [data-ai-action]').click(); await page.locator('#item-response [data-ai-feedback]:not([hidden])').waitFor();
    const spokenSent = sent.at(-1);
    ok(spokenSent.transcript === 'Reliable public transport helps me reach my classes on time.' && spokenSent.response === 'I want to speak more clearly next time.', 'Spoken transcript and reflection notes remain separate in the API request');
    ok((await page.locator('#item-response [data-ai-feedback]').innerText()).includes('70%') && (await page.locator('#item-response .ai-review-disclosure').innerText()).includes('pronunciation, fluency and delivery are not assessed'), 'Speaking feedback labels its content/language percentage and acoustic limits');
    await page.locator('#item-response [data-ai-transcript]').fill('A changed transcript requires a separate review.');
    ok(await page.locator('#item-response [data-ai-feedback]').isHidden(), 'Changing the spoken transcript hides coaching for the earlier source');
    await page.locator('#item-response [data-ai-source]').selectOption('file');
    await page.locator('#item-response [data-ai-file]').setInputFiles({ name: 'student-recording.wav', mimeType: 'audio/wav', buffer: wav() });
    await page.locator('#item-response [data-ai-action]').click(); await page.locator('#item-response [data-ai-feedback]:not([hidden])').waitFor();
    const converted = audioSent.at(-1);
    ok(audioSent.length === 1 && converted.filename === 'response.wav' && converted.mime === 'audio/wav' && converted.bytes.toString('ascii', 0, 4) === 'RIFF' && converted.bytes.readUInt32LE(24) === 16000 && converted.bytes.readUInt16LE(22) === 1 && converted.bytes.readUInt16LE(34) === 16, 'The real browser audio pipeline converts an uploaded clip to bounded 16 kHz mono PCM WAV');
    await page.locator('#item-response .ai-review-transcript-result summary').click();
    ok(await page.locator('#item-response .ai-review-transcript-result p').isVisible() && await page.locator('#item-response .ai-review-transcript-result p').textContent() === 'Reliable public transport helps me reach my classes on time.', 'Audio feedback persists visibly with the actual transcript supplied by transcription');
    async function recordingReview(seconds, scenario) {
        await page.locator('#item-response [data-ai-file]').setInputFiles({ name: `retained-${seconds}.wav`, mimeType: 'audio/wav', buffer: wav(seconds) });
        responseScenario = scenario;
        await page.locator('#item-response [data-ai-action]').click();
    }
    let postsBefore = sent.length, getsBefore = statusRequests.length;
    await recordingReview(2, 'drop-completed');
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'Recovered the completed review' }).waitFor();
    ok(sent.length === postsBefore + 1 && statusRequests.length === getsBefore + 1 && await page.locator('#item-response [data-ai-feedback]').isVisible(), 'A dropped speaking response recovers its saved feedback with one read-only check and no second provider submission');
    postsBefore = sent.length; getsBefore = statusRequests.length;
    await recordingReview(3, 'drop-missing');
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'connection ended' }).waitFor();
    const missingId = sent.at(-1).request_id;
    const missingBytes = audioSent.at(-1).bytes;
    ok(sent.length === postsBefore + 1 && statusRequests.length === getsBefore + 1 && !(await page.locator('#item-response [data-ai-status]').innerText()).includes('Failed to fetch') && await page.locator('#item-response [data-ai-feedback]').isHidden() && await page.locator('#item-response [data-ai-file]').evaluate(input => input.files[0].name === 'retained-3.wav'), 'A connection reset with no saved review retains the recording and explains recovery without exposing raw browser errors or a fake score');
    await page.locator('#item-response [data-ai-action]').click();
    await page.locator('#item-response [data-ai-feedback]:not([hidden])').waitFor();
    ok(sent.at(-1).request_id === missingId && audioSent.at(-1).bytes.equals(missingBytes), 'An explicit retry reuses the same review reference and exact prepared WAV for unchanged audio');
    postsBefore = sent.length;
    await recordingReview(4, 'drop-pending');
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'still processing' }).waitFor();
    const pendingId = sent.at(-1).request_id;
    await page.locator('#item-response [data-ai-action]').click();
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'still processing' }).waitFor();
    ok(sent.length === postsBefore + 1 && await page.locator('#item-response [data-ai-action]').innerText() === 'Check AI review status', 'Pending reviews are checked without uploading the recording again');
    statusReplies.set(pendingId, { ...statusReplies.get(pendingId), state: 'completed' });
    await page.locator('#item-response [data-ai-action]').click();
    await page.locator('#item-response [data-ai-feedback]:not([hidden])').waitFor();
    ok(sent.length === postsBefore + 1 && (await page.locator('#item-response [data-ai-status]').innerText()).includes('Recovered'), 'A pending recording review becomes visible on a later explicit completed-status check');
    await recordingReview(5, 'drop-pending');
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'still processing' }).waitFor();
    const changedAudioId = sent.at(-1).request_id;
    await page.locator('#item-response [data-ai-file]').setInputFiles({ name: 'changed-after-submit.wav', mimeType: 'audio/wav', buffer: wav(6) });
    statusReplies.set(changedAudioId, { ...statusReplies.get(changedAudioId), state: 'completed' });
    await page.locator('#item-response [data-ai-action]').click();
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'changed while' }).waitFor();
    ok(await page.locator('#item-response [data-ai-feedback]').isHidden(), 'Recovered audio feedback is not attached to a different recording selected while the original review was pending');
    postsBefore = sent.length;
    await recordingReview(7, 'drop-pending');
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'still processing' }).waitFor();
    const restoredId = sent.at(-1).request_id;
    uiAvailability.configured = false; uiAvailability.transportAvailable = false;
    await page.reload(); await page.locator('#item-response .ai-review-panel').waitFor();
    ok(await page.locator('#item-response [data-ai-file]').evaluate(input => input.files.length === 0) && await page.locator('#item-response [data-ai-action]').isEnabled(), 'After reload a saved review can still be checked without the original file or currently enabled provider setup');
    statusReplies.set(restoredId, { ...statusReplies.get(restoredId), state: 'completed' });
    await page.locator('#item-response [data-ai-action]').click();
    await page.locator('#item-response [data-ai-feedback]:not([hidden])').waitFor();
    ok(sent.length === postsBefore + 1 && await page.locator('#item-response [data-ai-action]').isDisabled(), 'Read-only recovery restores saved audio feedback while new paid reviews remain disabled');
    uiAvailability.configured = true; uiAvailability.transportAvailable = true;
    await page.reload(); await page.locator('#item-response .ai-review-panel').waitFor();
    getsBefore = statusRequests.length;
    await recordingReview(8, 'html413');
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'rejected the recording upload as too large' }).waitFor();
    ok(statusRequests.length === getsBefore && await page.locator('#item-response [data-ai-feedback]').isHidden(), 'A hosting HTML 413 response explains upload limits and does not pretend that scoring completed');
    postsBefore = sent.length; getsBefore = statusRequests.length;
    await recordingReview(9, 'html504');
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'did not return AI feedback in time' }).waitFor();
    ok(sent.length === postsBefore + 1 && statusRequests.length === getsBefore + 1, 'A hosting HTML gateway timeout checks saved status before allowing an explicit resubmission');
    await recordingReview(10, 'in-progress');
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'still processing' }).waitFor();
    const inProgressId = sent.at(-1).request_id;
    postsBefore = sent.length;
    statusReplies.set(inProgressId, { state: 'failed', code: 'ai_pending_expired', error: 'The earlier review did not finish. Retry the same request.' });
    await page.locator('#item-response [data-ai-action]').click();
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'earlier review did not finish' }).waitFor();
    ok(sent.length === postsBefore && await page.locator('#item-response [data-ai-action]').innerText() === 'Retry AI review', 'Structured in-progress replies use status checks, and a confirmed failed review requires a separate explicit retry');
    uiAvailability.maxAudioBytes = 2 * 1024 * 1024;
    await page.reload(); await page.locator('#item-response .ai-review-panel').waitFor();
    postsBefore = sent.length;
    await recordingReview(75, null);
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'above this website’s 2.00 MB upload limit' }).waitFor();
    ok(sent.length === postsBefore && (await page.locator('#item-response [data-ai-upload-info]').innerText()).includes('2.29 MB') && await page.locator('#item-response [data-ai-file]').evaluate(input => input.files.length === 1), 'A converted WAV above the actual PHP upload limit is stopped before POST, with its prepared size shown and original recording retained');
    uiAvailability.maxAudioBytes = 10 * 1024 * 1024;
    await page.reload(); await page.locator('#item-response .ai-review-panel').waitFor();
    const callsBeforeBadAudio = sent.length;
    await page.locator('#item-response [data-ai-file]').setInputFiles({ name: 'corrupt-recording.wav', mimeType: 'audio/wav', buffer: Buffer.from('not-real-audio'.repeat(30)) });
    ok(await page.locator('#item-response [data-ai-feedback]').isHidden(), 'Selecting a new recording hides feedback for the previous recording');
    await page.locator('#item-response [data-ai-action]').click();
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'could not decode' }).waitFor();
    ok(sent.length === callsBeforeBadAudio, 'Undecodable recordings fail locally before any transcription or provider request');
    await page.locator('#item-response [data-ai-file]').setInputFiles({ name: 'too-short.wav', mimeType: 'audio/wav', buffer: wav(0.25) });
    await page.locator('#item-response [data-ai-action]').click();
    await page.locator('#item-response [data-ai-status]').filter({ hasText: 'half a second' }).waitFor();
    ok(sent.length === callsBeforeBadAudio, 'Clips shorter than half a second fail before provider upload');
    await page.setViewportSize({ width: 390, height: 844 });
    ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'AI review remains usable without page overflow at a mobile width');
    await start('ielts-academic-01');
    const writingState = await page.evaluate(exam => {
        const data = JSON.parse(document.querySelector('#exam-data').textContent);
        if (data.exams[exam.id].version !== exam.version) throw new Error('The timed fixture must match the server-reserved paper version.');
        const key = `nisha:fullmock:${data.userScope}:${exam.id}:${exam.version}`;
        const state = JSON.parse(localStorage.getItem(key));
        const sectionIndex = exam.sections.findIndex(section => section.items.some(item => item.type === 'essay'));
        return { key, state: { ...state, sectionIndex, itemIndex: 0, sectionDeadline: Date.now() + exam.sections[sectionIndex].duration_seconds * 1000 }, marker: crypto.randomUUID() };
    }, fullAcademic);
    await page.addInitScript(({ key, state, marker }) => { if (!sessionStorage.getItem(marker)) { localStorage.setItem(key, JSON.stringify(state)); sessionStorage.setItem(marker, 'used'); } }, writingState);
    await page.reload(); await page.locator('.exam-writing-area').waitFor();
    ok(await page.locator('.ai-review-panel').count() === 0, 'Timed mocks do not expose AI coaching while the test is running');
    const persistedPayload = request(exam, item);
    execFileSync(php, ['-r', `
require 'includes/ai-review-service.php';
$s=db()->prepare('SELECT id FROM users WHERE email=?'); $s->execute([$argv[1]]); $user=['id'=>(int)$s->fetchColumn()];
$payload=json_decode($argv[2],true,12,JSON_THROW_ON_ERROR); $exam=nisha_resolve_exam($payload['exam_id']); $item=nisha_exam_items($exam)[$payload['item_id']];
$rubric=nisha_ai_rubric($exam,$item,'writing');
$raw=['assessable'=>true,'summary'=>'<img src=x onerror="window.__historyXss=1"> Saved learning feedback.',
'criteria'=>array_map(static fn($c)=>['id'=>$c['id'],'score'=>6,'feedback'=>'Support the main idea with an example.'],$rubric['criteria']),
'strengths'=>['A relevant central idea.'],'improvements'=>[['issue'=>'Develop the example.','evidence'=>'public transport','suggestion'=>'Explain a practical benefit.','example'=>'Frequent buses reduce waiting times.']],
'next_steps'=>['Add an example.','Check grammar.','Review the task.'],'improved_sample'=>'Learning example: Frequent buses help students arrive on time.','limitations'=>[]];
nisha_ai_submit($user,$payload,null,static fn()=>['status'=>200,'body'=>json_encode(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($raw)]]]]])]);
`, email, JSON.stringify(persistedPayload)], { cwd: root, stdio: 'pipe' });
    const completedStatus = await page.request.get(`${base}/api/ai-review.php?request_id=${persistedPayload.request_id}`);
    const completedData = await completedStatus.json();
    ok(completedStatus.status() === 200 && completedData.state === 'completed' && completedData.entry.response === responseText && completedData.entry.attempt_id === persistedPayload.attempt_id, 'The real owner-only status endpoint returns a persisted completed review while provider setup is disabled');
    await page.goto(`${base}/ai-reviews.php`);
    const history = page.locator('.ai-history-review');
    ok(await history.count() === 1 && (await history.locator('summary').first().innerText()).includes('AI practice estimate'), 'A real persisted provider fixture appears in the student’s coaching history');
    await history.locator('summary').first().click();
    ok((await history.innerText()).includes(responseText) && (await history.innerText()).includes('Your next steps') && await history.locator('img,script').count() === 0 && await page.evaluate(() => window.__historyXss) === undefined, 'Saved coaching restores the original answer and guidance while escaping model HTML');
    const guestContext = await browser.newContext();
    try {
        const guestPage = await guestContext.newPage(); await guestPage.goto(`${base}/ai-reviews.php`);
        ok(new URL(guestPage.url()).pathname === '/login.php' && await guestPage.locator('.ai-history-review').count() === 0, 'Saved coaching history requires the student’s authenticated session');
        await enableUiFixture(guestContext);
        let guestReviewCalls = 0;
        guestPage.on('request', request => { if (new URL(request.url()).pathname === '/api/ai-review.php') guestReviewCalls++; });
        await guestPage.goto(`${base}/exams.php?exam=${encodeURIComponent(exam.id)}&start=1`);
        await guestPage.locator('#exam-setup-dialog').waitFor({ state: 'visible' });
        await guestPage.locator('#ready-confirmed').check();
        await guestPage.locator('#begin-exam').click();
        await guestPage.locator('.exam-writing-area').fill(responseText);
        await guestPage.locator('#submit-exam-early').click();
        await guestPage.locator('#exam-confirm-dialog[open]').waitFor();
        await guestPage.locator('#exam-confirm-yes').click();
        await guestPage.locator('#exam-result:not([hidden])').waitFor();
        ok(await guestPage.locator('#result-ai-status').getAttribute('data-ai-state') === 'guest' && (await guestPage.locator('.exam-review-points').innerText()).includes('Sign in for AI review') && await guestPage.locator('#result-ai-sign-in').isVisible(), 'A configured website identifies the guest account requirement rather than claiming AI setup is missing');
        await guestPage.locator('#result-ai-action').click();
        ok(await guestPage.locator('#exam-result .ai-review-panel').isVisible() && await guestPage.locator('#exam-result [data-ai-action]').isDisabled() && guestReviewCalls === 0, 'Configured guest results expose the sign-in panel without automatically sending their answer to OpenAI');
    } finally { await guestContext.close(); }
    ok(errors.length === 0 && external.length === 0 && !/Fatal error|Uncaught/.test(serverErrors), 'Browser and HTTP flows finish without script/server failures or external requests');
    console.log(`PASS: ${checks} AI HTTP/browser checks. Provider UI responses were explicitly intercepted; no paid calls.`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (browser) await browser.close();
    if (server) { server.kill('SIGTERM'); await new Promise(resolve => { if (server.exitCode !== null) resolve(); else { server.once('exit', resolve); setTimeout(resolve, 2000); } }); }
    try {
        if (email) execFileSync(php, ['-r', `require 'includes/bootstrap.php'; $pdo=db(); $pdo->beginTransaction(); $s=$pdo->prepare('SELECT id FROM users WHERE email=? FOR UPDATE'); $s->execute([$argv[1]]); $id=$s->fetchColumn(); if($id){ $s=$pdo->prepare('SELECT usage_day,used_count FROM ai_review_usage WHERE scope_key=?'); $s->execute(['user-'.$id]); foreach($s->fetchAll() as $row){ $d=$pdo->prepare('UPDATE ai_review_usage SET used_count=GREATEST(used_count-?,0) WHERE usage_day=? AND scope_key=?'); $d->execute([$row['used_count'],$row['usage_day'],'global']); } $s=$pdo->prepare('DELETE FROM ai_review_usage WHERE scope_key=?'); $s->execute(['user-'.$id]); $s=$pdo->prepare('DELETE FROM users WHERE id=? AND email=?'); $s->execute([$id,$argv[1]]); } $pdo->commit();`, email], { cwd: root, stdio: 'pipe' });
    } finally { fs.rmSync(artifacts, { recursive: true, force: true }); }
});
