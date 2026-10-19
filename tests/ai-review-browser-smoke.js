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
    await page.locator('#exam-setup-dialog').waitFor({ state: 'visible' });
    await page.locator('#assisted-mode').check(); await page.locator('#ready-confirmed').check();
    await page.locator('#begin-exam').click();
    await page.locator('#exam-runner:not([hidden])').waitFor();
}
function reviewEntry(payload, transcript) {
    const spoken = typeof transcript === 'string';
    return { id: ++reviewEntry.id, exam_id: payload.exam_id, exam_version: payload.exam_version, item_id: payload.item_id, attempt_id: payload.attempt_id, response: payload.response,
        review: { mode: spoken ? 'speaking-transcript' : 'writing', score: { label: spoken ? 'Speaking content and language review' : 'IELTS Writing practice band estimate', value: spoken ? 70 : 6.5, max: spoken ? 100 : 9, unit: spoken ? '%' : 'band' },
            criteria: [{ label: 'Task response', score: spoken ? 70 : 6.5, max: spoken ? 100 : 9, feedback: 'Develop one specific example.' }],
            summary: '<img src=x onerror="window.__aiXss=1"> A clear central idea.', strengths: ['Relevant main idea.'], improvements: [{ issue: 'Develop the example.', evidence: 'public transport', suggestion: 'Explain how this benefits students.', example: 'A frequent bus route reduces waiting time.' }],
            next_steps: ['Add one specific example.', 'Check sentence boundaries.', 'Review the word range.'], improved_sample: '<script>window.__aiXss=2</script> Learning example: Public transport helps students arrive on time.',
            limitations: ['AI-generated practice estimate; not an official result.', ...(spoken ? ['Pronunciation and oral fluency were not assessed.'] : [])], transcript: spoken ? transcript : null, word_count: 18, model: 'deterministic-browser-fixture', reviewed_at: new Date().toISOString() } };
}
reviewEntry.id = 1000;
async function enableUiFixture() {
    // Only the browser copy of public availability is changed. The actual HTTP
    // endpoint remains unconfigured; subsequent UI calls are explicitly intercepted.
    await context.route('**/exams.php?*', async route => {
        const response = await route.fetch();
        let body = await response.text();
        body = body.replace(/(<script id="exam-data" type="application\/json">)([\s\S]*?)(<\/script>)/, (_, open, json, close) => {
            const value = JSON.parse(json); value.aiReview = { configured: true, transportAvailable: true, studentDailyLimit: 10 };
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
    const { exam, item } = config.sessions.essay;
    await start(exam.id);
    await page.locator('.exam-writing-area').fill(responseText);
    const guestPanel = page.locator('.ai-review-panel');
    await guestPanel.waitFor();
    ok(await guestPanel.locator('[data-ai-action]').isDisabled() && (await guestPanel.innerText()).includes('Sign in'), 'Guests see an honest sign-in requirement and cannot request paid AI');
    const guest = await api(request(exam, item));
    ok(guest.status === 401 && guest.body.code === 'ai_login_required', 'HTTP rejects guest AI requests before provider processing');
    const method = await page.request.get(`${base}/api/ai-review.php`);
    ok(method.status() === 405 && method.headers().allow === 'POST', 'AI endpoint accepts POST only');
    email = `ai-browser-${crypto.randomBytes(10).toString('hex')}@example.test`;
    const password = crypto.randomBytes(24).toString('hex');
    await page.goto(`${base}/register.php`);
    await page.locator('#name').fill('AI browser fixture'); await page.locator('#email').fill(email); await page.locator('#password').fill(password); await page.locator('#password_confirmation').fill(password);
    await page.locator('button[type="submit"]').click(); await page.waitForURL('**/dashboard.php');
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
    ok(await page.locator('[data-ai-action]').isDisabled() && (await page.locator('[data-ai-availability]').innerText()).includes('awaiting'), 'Signed-in learners see that AI still needs the owner’s API setup');
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
    let failFirst = true;
    let holdResolve;
    let holdResponse = false;
    await context.route('**/api/ai-review.php', async route => {
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
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, saved: true, cached: false, entry, usage: { remaining_today: 9, limit: 10, resets: '00:00 UTC' } }) });
    });
    await start(exam.id); await page.locator('.exam-writing-area').fill(responseText);
    await page.locator('[data-ai-action]').click();
    await page.locator('[data-ai-status]').filter({ hasText: 'temporarily unavailable' }).waitFor();
    ok(await page.locator('.exam-writing-area').inputValue() === responseText && await page.locator('[data-ai-feedback]').isHidden(), 'A provider failure keeps the written answer and shows no score');
    await page.locator('[data-ai-action]').click(); await page.locator('[data-ai-feedback]:not([hidden])').waitFor();
    ok(sent.length === 2 && sent[0].request_id === sent[1].request_id, 'Retry reuses the same review reference for unchanged work');
    ok((await page.locator('[data-ai-feedback]').innerText()).includes('6.5 / 9') && await page.locator('.ai-review-criterion').count() === 1 && (await page.locator('[data-ai-feedback]').innerText()).includes('Your next practice steps'), 'AI feedback renders its estimate, rubric and actionable improvement plan');
    ok(await page.locator('[data-ai-feedback] img, [data-ai-feedback] script').count() === 0 && await page.evaluate(() => window.__aiXss) === undefined, 'Model feedback and learning samples render as safe text');
    const changed = responseText + ' Students can therefore plan their mornings more reliably.';
    await page.locator('.exam-writing-area').fill(changed);
    ok(await page.locator('[data-ai-feedback]').isHidden(), 'Editing the written response hides feedback for the earlier answer');
    holdResponse = true; await page.locator('[data-ai-action]').click();
    await page.waitForFunction(() => document.querySelector('.ai-review-panel').getAttribute('aria-busy') === 'true');
    for (let count = 0; !holdResolve && count < 100; ++count) await new Promise(resolve => setTimeout(resolve, 20));
    assert(holdResolve, 'The held request must reach the route');
    await page.locator('.exam-writing-area').fill(changed + ' A further edit during review.');
    holdResolve(); holdResponse = false;
    await page.locator('[data-ai-status]').filter({ hasText: 'changed while' }).waitFor();
    ok(await page.locator('[data-ai-feedback]').isHidden() && sent[2].request_id !== sent[1].request_id, 'Late feedback stays attached to its original answer and changed work gets a new request reference');
    await start(config.sessions.spoken.exam.id);
    await page.locator('.exam-speaking-notes').fill('I want to speak more clearly next time.');
    ok(await page.locator('[data-ai-action]').isDisabled(), 'Speaking reflections alone cannot trigger an AI language review');
    await page.locator('[data-ai-transcript]').fill('Reliable public transport helps me reach my classes on time.');
    await page.locator('[data-ai-action]').click(); await page.locator('[data-ai-feedback]:not([hidden])').waitFor();
    const spokenSent = sent.at(-1);
    ok(spokenSent.transcript === 'Reliable public transport helps me reach my classes on time.' && spokenSent.response === 'I want to speak more clearly next time.', 'Spoken transcript and reflection notes remain separate in the API request');
    ok((await page.locator('[data-ai-feedback]').innerText()).includes('70%') && (await page.locator('.ai-review-disclosure').innerText()).includes('pronunciation, fluency and delivery are not assessed'), 'Speaking feedback labels its content/language percentage and acoustic limits');
    await page.locator('[data-ai-transcript]').fill('A changed transcript requires a separate review.');
    ok(await page.locator('[data-ai-feedback]').isHidden(), 'Changing the spoken transcript hides coaching for the earlier source');
    await page.locator('[data-ai-source]').selectOption('file');
    await page.locator('[data-ai-file]').setInputFiles({ name: 'student-recording.wav', mimeType: 'audio/wav', buffer: wav() });
    await page.locator('[data-ai-action]').click(); await page.locator('[data-ai-feedback]:not([hidden])').waitFor();
    const converted = audioSent.at(-1);
    ok(audioSent.length === 1 && converted.filename === 'response.wav' && converted.mime === 'audio/wav' && converted.bytes.toString('ascii', 0, 4) === 'RIFF' && converted.bytes.readUInt32LE(24) === 16000 && converted.bytes.readUInt16LE(22) === 1 && converted.bytes.readUInt16LE(34) === 16, 'The real browser audio pipeline converts an uploaded clip to bounded 16 kHz mono PCM WAV');
    await page.locator('.ai-review-transcript-result summary').click();
    ok(await page.locator('.ai-review-transcript-result p').isVisible() && await page.locator('.ai-review-transcript-result p').textContent() === 'Reliable public transport helps me reach my classes on time.', 'Audio feedback persists visibly with the actual transcript supplied by transcription');
    const callsBeforeBadAudio = sent.length;
    await page.locator('[data-ai-file]').setInputFiles({ name: 'corrupt-recording.wav', mimeType: 'audio/wav', buffer: Buffer.from('not-real-audio'.repeat(30)) });
    ok(await page.locator('[data-ai-feedback]').isHidden(), 'Selecting a new recording hides feedback for the previous recording');
    await page.locator('[data-ai-action]').click();
    await page.locator('[data-ai-status]').filter({ hasText: 'could not decode' }).waitFor();
    ok(sent.length === callsBeforeBadAudio, 'Undecodable recordings fail locally before any transcription or provider request');
    await page.locator('[data-ai-file]').setInputFiles({ name: 'too-short.wav', mimeType: 'audio/wav', buffer: wav(0.25) });
    await page.locator('[data-ai-action]').click();
    await page.locator('[data-ai-status]').filter({ hasText: 'half a second' }).waitFor();
    ok(sent.length === callsBeforeBadAudio, 'Clips shorter than half a second fail before provider upload');
    await page.setViewportSize({ width: 390, height: 844 });
    ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'AI review remains usable without page overflow at a mobile width');
    await start('ielts-academic-01');
    const writingState = await page.evaluate(() => {
        const data = JSON.parse(document.querySelector('#exam-data').textContent);
        const exam = data.exams['ielts-academic-01'];
        const key = `nisha:fullmock:${data.userScope}:${exam.id}:${exam.version}`;
        const state = JSON.parse(localStorage.getItem(key));
        const sectionIndex = exam.sections.findIndex(section => section.items.some(item => item.type === 'essay'));
        return { key, state: { ...state, sectionIndex, itemIndex: 0, sectionDeadline: Date.now() + exam.sections[sectionIndex].duration_seconds * 1000 }, marker: crypto.randomUUID() };
    });
    await page.addInitScript(({ key, state, marker }) => { if (!sessionStorage.getItem(marker)) { localStorage.setItem(key, JSON.stringify(state)); sessionStorage.setItem(marker, 'used'); } }, writingState);
    await page.reload(); await page.locator('.exam-writing-area').waitFor();
    ok(await page.locator('.ai-review-panel').count() === 0, 'Timed mocks do not expose AI coaching while the test is running');
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
`, email, JSON.stringify(request(exam, item))], { cwd: root, stdio: 'pipe' });
    await page.goto(`${base}/ai-reviews.php`);
    const history = page.locator('.ai-history-review');
    ok(await history.count() === 1 && (await history.locator('summary').first().innerText()).includes('AI practice estimate'), 'A real persisted provider fixture appears in the student’s coaching history');
    await history.locator('summary').first().click();
    ok((await history.innerText()).includes(responseText) && (await history.innerText()).includes('Your next steps') && await history.locator('img,script').count() === 0 && await page.evaluate(() => window.__historyXss) === undefined, 'Saved coaching restores the original answer and guidance while escaping model HTML');
    const guestContext = await browser.newContext();
    try {
        const guestPage = await guestContext.newPage(); await guestPage.goto(`${base}/ai-reviews.php`);
        ok(new URL(guestPage.url()).pathname === '/login.php' && await guestPage.locator('.ai-history-review').count() === 0, 'Saved coaching history requires the student’s authenticated session');
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
