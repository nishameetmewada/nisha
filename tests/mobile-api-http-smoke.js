/* Isolated local mobile API checks. No live students, keys or provider calls. */
'use strict';
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { execFileSync, spawn } = require('node:child_process');

const root = path.resolve(__dirname, '..');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const mysql = process.env.NISHA_TEST_MYSQL_ADMIN_BIN || '/workspace/.tools/bin/mariadb';
const suffix = crypto.randomBytes(7).toString('hex');
const database = `nisha_mobile_http_${suffix}`;
const databaseUser = `mobile_http_${suffix}`;
const databasePassword = crypto.randomBytes(24).toString('hex');
const password = crypto.randomBytes(20).toString('hex');
const emails = { first: `mobile-first-${suffix}@example.test`, second: `mobile-second-${suffix}@example.test`, limit: `mobile-limit-${suffix}@example.test` };
const scratch = fs.mkdtempSync(path.join(os.tmpdir(), 'nisha-mobile-http-'));
let server, base, checks = 0, created = false, stderr = '';
const ok = (condition, label) => { assert(condition, label); checks++; console.log('PASS', label); };
function sql(source, output = false) {
    try {
        return execFileSync(mysql, output ? ['--batch', '--skip-column-names'] : [], { input: source, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    } catch (_) { throw new Error('The isolated mobile database fixture could not be prepared or cleaned.'); }
}
function fixture(source, values = {}) {
    try {
        return JSON.parse(execFileSync(php, ['-d', `auto_prepend_file=${path.join(scratch, 'prepend.php')}`, '-r', `
define('NISHA_ROOT',$argv[1]);require 'includes/mobile-auth.php';require 'includes/learning-catalog.php';
try{$v=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);${source}session_write_close();}
catch(Throwable$e){fwrite(STDERR,'Local mobile fixture failed safely.');exit(1);}
`, scratch], { cwd: root, input: JSON.stringify(values), encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }));
    } catch (_) { throw new Error('An isolated mobile fixture operation failed.'); }
}
function noAnswers(value) {
    return !value || typeof value !== 'object' || Object.entries(value).every(([key, child]) => !['answer', 'explanation', 'correct_text'].includes(key) && noAnswers(child));
}
function secretFree(value) { return !/password_hash|\$2[ayb]\$|sk_(?:live|test)_[A-Za-z0-9]|whsec_/i.test(JSON.stringify(value)); }
async function request(action, options = {}) {
    const url = new URL('/api/mobile.php', base); url.searchParams.set('action', action);
    for (const [key, value] of Object.entries(options.query || {})) url.searchParams.set(key, String(value));
    const headers = { ...(options.headers || {}) };
    if (options.token) headers.Authorization = `Bearer ${options.token}`;
    if (options.origin !== undefined) headers.Origin = options.origin;
    if (options.cookie) headers.Cookie = options.cookie;
    if (options.data !== undefined) headers['Content-Type'] = 'application/json';
    const response = await fetch(url, { method: options.method || (options.data === undefined ? 'GET' : 'POST'), headers, body: options.raw === undefined ? (options.data === undefined ? undefined : JSON.stringify(options.data)) : options.raw, redirect: 'manual' });
    const text = await response.text();
    let body; try { body = text ? JSON.parse(text) : null; } catch (_) { throw new Error(`Mobile ${action} returned a non-JSON response.`); }
    return { status: response.status, body, headers: response.headers };
}
function fullSubmission(exam, attemptId) {
    return { exam_id: exam.id, exam_version: exam.version, attempt_id: attemptId, answers: {}, elapsed_seconds: 12, completed_sections: [], assisted: true };
}
async function login(email, secret = password) { return request('login', { data: { email, password: secret, device_name: 'Local HTTP fixture' } }); }
async function websiteSession(email) {
    let response = await fetch(`${base}/login.php`, { redirect: 'manual' });
    const html = await response.text(); const token = html.match(/name="csrf_token" value="([a-f0-9]{64})"/)?.[1];
    const firstCookie = response.headers.getSetCookie().map(value => value.split(';')[0]).join('; ');
    assert(token, 'The isolated website form must expose its CSRF field.');
    response = await fetch(`${base}/login.php`, { method: 'POST', headers: { Cookie: firstCookie }, body: new URLSearchParams({ email, password, csrf_token: token }), redirect: 'manual' });
    assert.equal(response.status, 303, 'The isolated website account must sign in.');
    return response.headers.getSetCookie().map(value => value.split(';')[0]).join('; ') || firstCookie;
}

(async () => {
    fs.mkdirSync(path.join(scratch, 'config')); fs.mkdirSync(path.join(scratch, 'sessions'));
    const config = { db_host: '127.0.0.1', db_port: 3306, db_name: database, db_user: databaseUser, db_password: databasePassword, admin_user_ids: [], openai_api_key: '', payments_enabled: false };
    const configSource = `<?php return json_decode(${JSON.stringify(JSON.stringify(config))},true,16,JSON_THROW_ON_ERROR);`;
    fs.writeFileSync(path.join(scratch, 'config', 'config.php'), configSource, { mode: 0o600 });
    fs.writeFileSync(path.join(scratch, 'prepend.php'), `<?php define('NISHA_ROOT',${JSON.stringify(scratch)});ini_set('session.save_path',${JSON.stringify(path.join(scratch, 'sessions'))});`, { mode: 0o600 });
    sql(`CREATE DATABASE \`${database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '${databaseUser}'@'127.0.0.1' IDENTIFIED BY '${databasePassword}'; GRANT SELECT,INSERT,UPDATE,DELETE ON \`${database}\`.* TO '${databaseUser}'@'127.0.0.1';`);
    created = true;
    sql(`USE \`${database}\`;\n${fs.readFileSync(path.join(root, 'database/schema.sql'), 'utf8')}\n${fs.readFileSync(path.join(root, 'database/migrations/010_mobile_app.sql'), 'utf8')}`);
    const probe = net.createServer(); await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
    const port = probe.address().port; await new Promise(resolve => probe.close(resolve)); base = `http://127.0.0.1:${port}`;
    server = spawn(php, ['-d', `auto_prepend_file=${path.join(scratch, 'prepend.php')}`, '-S', `127.0.0.1:${port}`, '-t', root], { cwd: root, env: { ...process.env, NISHA_OPENAI_API_KEY: '', OPENAI_API_KEY: '', NISHA_STRIPE_SECRET_KEY: '', NISHA_STRIPE_WEBHOOK_SECRET: '', NISHA_PAYMENTS_ENABLED: '0', NISHA_MOBILE_DEVELOPMENT_ORIGINS: 'http://127.0.0.1:4173,https://evil.example' }, stdio: ['ignore', 'ignore', 'pipe'] });
    server.stderr.on('data', data => { stderr += data; });
    let ready = false;
    for (let i = 0; i < 60; i++) {
        try { const reply = await request('health'); if (reply.status === 200) { ready = true; break; } } catch (_) {}
        await new Promise(resolve => setTimeout(resolve, 50));
    }
    assert(ready, 'The isolated mobile HTTP server must start.');

    let reply = await request('health', { origin: 'https://localhost' });
    ok(reply.status === 200 && reply.body.mobile_ready && reply.body.protocol_version === 1 && secretFree(reply.body), 'Mobile health exposes protocol readiness without configuration secrets');
    ok(reply.headers.get('access-control-allow-origin') === 'https://localhost' && reply.headers.get('access-control-allow-credentials') === null && !reply.headers.has('set-cookie'), 'Native CORS uses the exact origin without cookies or credential reflection');
    reply = await request('health', { method: 'OPTIONS', origin: 'capacitor://localhost', headers: { 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'authorization, content-type' } });
    ok(reply.status === 204 && reply.headers.get('access-control-allow-origin') === 'capacitor://localhost', 'The iOS native origin can preflight bearer JSON requests');
    reply = await request('health', { origin: 'http://127.0.0.1:4173' });
    ok(reply.status === 200 && reply.headers.get('access-control-allow-origin') === 'http://127.0.0.1:4173', 'A privately opted-in loopback development origin is allowed');
    reply = await request('health', { origin: 'https://evil.example' });
    ok(reply.status === 403 && !reply.headers.has('access-control-allow-origin'), 'An arbitrary origin remains forbidden even if inserted into development-origin configuration');
    reply = await request('health', { method: 'OPTIONS', origin: 'https://localhost', headers: { 'Access-Control-Request-Method': 'DELETE' } });
    ok(reply.status === 405, 'CORS preflight cannot enable an unsupported state-changing method');
    reply = await request('health', { method: 'OPTIONS', origin: 'https://localhost', headers: { 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'x-admin-override' } });
    ok(reply.status === 403, 'CORS preflight rejects unexpected privileged headers');
    reply = await request('login', { method: 'GET' }); ok(reply.status === 405, 'Login credentials cannot be sent through the query string');
    reply = await request('login', { method: 'POST', raw: 'email=x', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }); ok(reply.status === 415, 'Mobile authentication requires a JSON request body');
    reply = await request('login', { method: 'POST', raw: '{', headers: { 'Content-Type': 'application/json' } }); ok(reply.status === 400, 'Malformed JSON is rejected before authentication');

    reply = await request('register', { data: { name: 'First Mobile Fixture', email: emails.first, password, password_confirmation: password, device_name: 'Local fixture phone' } });
    assert.equal(reply.status, 201, 'A disposable student must register through the real mobile API.');
    const first = { id: reply.body.user.id, token: reply.body.access_token };
    ok(/^nm1_[a-f0-9]{64}$/.test(first.token) && reply.body.token_type === 'Bearer' && secretFree(reply.body) && !reply.headers.has('set-cookie'), 'Registration returns one opaque token and a safe student identity without browser sign-in');
    const stored = fixture(`$q=db()->prepare('SELECT token_hash FROM mobile_access_tokens WHERE user_id=?');$q->execute([$v['user_id']]);$h=$q->fetchColumn();echo json_encode(['hashed'=>$h===hash('sha256',$v['token']),'plaintext'=>$h===$v['token']]);`, { user_id: first.id, token: first.token });
    ok(stored.hashed && !stored.plaintext, 'The database stores a token hash rather than the usable bearer secret');
    reply = await request('me', { token: first.token });
    ok(reply.status === 200 && reply.body.user.id === first.id && !('password_hash' in reply.body.user) && !('role' in reply.body.user), 'A valid bearer token exposes only its own safe profile and access state');
    reply = await request('login', { headers: { Authorization: 'Bearer obsolete-client-value' }, data: { email: emails.first, password, device_name: 'Recovered local fixture' } });
    ok(reply.status === 200 && /^nm1_[a-f0-9]{64}$/.test(reply.body.access_token), 'Fresh valid credentials can recover from an obsolete malformed token header');
    first.token = reply.body.access_token;
    const browserCookie = await websiteSession(emails.first);
    reply = await request('me', { cookie: browserCookie });
    ok(reply.status === 401, 'An authenticated website cookie cannot substitute for a mobile bearer token');
    reply = await request('me', { headers: { Authorization: 'Bearer malformed' } }); ok(reply.status === 401, 'Malformed bearer tokens are rejected');
    reply = await request('register', { data: { name: 'Second Mobile Fixture', email: emails.second, password, password_confirmation: password } });
    assert.equal(reply.status, 201); const second = { id: reply.body.user.id, token: reply.body.access_token };
    ok(first.id !== second.id && first.token !== second.token, 'Separate accounts receive separate identities and mobile credentials');

    reply = await request('catalog');
    ok(reply.status === 200 && reply.body.courses.length === 4 && reply.body.full_mocks.length === 4 && noAnswers(reply.body), 'The public four-course catalogue contains metadata without answer keys');
    ok(reply.body.billing_mode === 'existing_access' && reply.body.purchase_in_app === false && !JSON.stringify(reply.body).includes('checkout.stripe.com'), 'Store app catalogue uses existing account access without exposing a web-purchase path');
    const canonical = fixture(`echo json_encode(nisha_resolve_exam('xp.ielts-academic.00001'),JSON_THROW_ON_ERROR);`);
    const question = canonical.sections[0].items[0];
    reply = await request('content', { query: { id: canonical.id } });
    ok(reply.status === 200 && reply.body.exam.id === canonical.id && noAnswers(reply.body.exam), 'Guest generated practice resolves to sanitized canonical server content');
    const practice = reply.body.exam;
    reply = await request('feedback', { data: { exam_id: practice.id, exam_version: practice.version, item_id: question.id, response: question.answer } });
    ok(reply.status === 200 && reply.body.review.score > 0 && typeof reply.body.review.explanation === 'string', 'Guest guided feedback marks the actual canonical response and returns its explanation');
    reply = await request('feedback', { data: { exam_id: practice.id, exam_version: 'stale', item_id: question.id, response: question.answer } });
    ok(reply.status === 409, 'A stale app content version cannot be marked against a newer key');
    reply = await request('content', { query: { id: 'xm.ielts-academic.0001' } });
    const mockMetadata = reply.body.exam;
    ok(reply.status === 200 && mockMetadata.requires_access && Object.keys(mockMetadata.resources).length === 0 && mockMetadata.sections.every(section => !('items' in section)), 'Full generated mocks expose metadata only before an authenticated start');
    reply = await request('feedback', { data: { exam_id: mockMetadata.id, exam_version: mockMetadata.version, item_id: question.id, response: question.answer } });
    ok(reply.status === 403, 'A full mock cannot use the guest single-question feedback endpoint');
    const attemptId = crypto.randomUUID();
    reply = await request('start_mock', { data: { exam_id: mockMetadata.id, attempt_id: attemptId }, cookie: browserCookie });
    ok(reply.status === 401, 'A website cookie cannot fetch a full mobile paper');
    reply = await request('start_mock', { data: { exam_id: mockMetadata.id, attempt_id: attemptId }, token: first.token });
    assert.equal(reply.status, 200); const full = reply.body.exam;
    ok(reply.body.grant.kind === 'trial' && full.sections.reduce((sum, section) => sum + section.items.length, 0) === 85 && noAnswers(full), 'The first native full mock reserves the shared free allowance and returns a sanitized paper');
    reply = await request('start_mock', { data: { exam_id: mockMetadata.id, attempt_id: attemptId }, token: first.token });
    ok(reply.status === 200 && reply.body.grant.attempt_id === attemptId, 'The same native attempt resumes its original free reservation');
    reply = await request('start_mock', { data: { exam_id: 'pte-core-01', attempt_id: crypto.randomUUID() }, token: first.token });
    ok(reply.status === 402 && !reply.body.exam, 'A parallel native attempt cannot reserve another free full mock');
    const submitted = fullSubmission(full, attemptId);
    reply = await request('complete', { data: submitted, token: first.token });
    assert.equal(reply.status, 200); const completed = reply.body;
    ok(completed.saved && completed.result_id && completed.mock_access.trial_state === 'consumed' && completed.result.content_source === 'generated', 'Native completion atomically saves the original generated result and consumes the trial');
    reply = await request('complete', { data: { ...submitted, elapsed_seconds: 99 }, token: first.token });
    ok(reply.status === 200 && reply.body.result_id === completed.result_id && JSON.stringify(reply.body.result) === JSON.stringify(completed.result), 'A retried native completion returns its immutable first saved result');
    reply = await request('start_mock', { data: { exam_id: 'pte-core-01', attempt_id: crypto.randomUUID() }, token: first.token });
    ok(reply.status === 402, 'A second native full mock requires the same paid pass as the website');
    reply = await request('result', { query: { id: completed.result_id }, token: second.token });
    ok(reply.status === 404 && !reply.body.result, 'Another account cannot read a saved native result');
    reply = await request('result', { query: { id: completed.result_id }, token: first.token });
    ok(reply.status === 200 && reply.body.result_id === completed.result_id && reply.body.result.content_source === 'generated', 'The owning student can reopen the frozen labelled result');
    reply = await request('history', { token: first.token, query: { page: 1, per_page: 20 } });
    ok(reply.status === 200 && reply.body.total === 1 && reply.body.items[0].id === completed.result_id && !('result_json' in reply.body.items[0]), 'History is account-scoped and returns bounded metadata');
    reply = await request('history', { token: first.token, query: { per_page: 500 } }); ok(reply.status === 422, 'History page size cannot be inflated by the app');
    reply = await request('coaching');
    ok(reply.status === 200 && reply.body.total === 0 && reply.body.available_total === 0 && !JSON.stringify(reply.body).includes('video_url'), 'An empty coaching library returns honest metadata without protected media URLs');
    reply = await request('checkout', { token: first.token, data: {} }); ok(reply.status === 404, 'The native API has no Stripe checkout action for store builds');

    fixture(`$q=db()->prepare("UPDATE mobile_access_tokens SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE token_hash=?");$q->execute([hash('sha256',$v['token'])]);echo json_encode(['done'=>true]);`, { token: second.token });
    reply = await request('me', { token: second.token }); ok(reply.status === 401, 'An expired native credential cannot access the profile');
    reply = await request('login', { token: second.token, data: { email: emails.second, password } });
    ok(reply.status === 200 && /^nm1_[a-f0-9]{64}$/.test(reply.body.access_token), 'Fresh valid credentials can replace an expired remembered device token');
    second.token = reply.body.access_token;
    fixture(`$q=db()->prepare("UPDATE account_security SET auth_version=auth_version+1 WHERE user_id=?");$q->execute([$v['id']]);echo json_encode(['done'=>true]);`, { id: second.id });
    reply = await request('me', { token: second.token }); ok(reply.status === 401, 'An administrator security-version change immediately invalidates native credentials');
    reply = await login(emails.second); assert.equal(reply.status, 200); second.token = reply.body.access_token;
    fixture(`$q=db()->prepare("UPDATE account_security SET status='suspended',auth_version=auth_version+1 WHERE user_id=?");$q->execute([$v['id']]);echo json_encode(['done'=>true]);`, { id: second.id });
    reply = await request('me', { token: second.token }); ok(reply.status === 401, 'Suspension rejects an already-issued mobile token');
    reply = await login(emails.second); ok(reply.status === 401 && !reply.body.access_token, 'Suspended students cannot obtain another native credential');
    fixture(`$q=db()->prepare("UPDATE account_security SET status='active' WHERE user_id=?");$q->execute([$v['id']]);echo json_encode(['done'=>true]);`, { id: second.id });
    reply = await login(emails.second); assert.equal(reply.status, 200); second.token = reply.body.access_token;
    reply = await request('logout', { token: second.token, data: {} }); ok(reply.status === 200, 'Logout revokes the presented device credential');
    reply = await request('me', { token: second.token }); ok(reply.status === 401, 'A logged-out credential cannot be replayed');

    reply = await request('deletion_request', { token: first.token, data: { password: 'wrong', confirmation: true } });
    ok(reply.status === 401, 'A deletion request requires the current account password');
    reply = await request('deletion_request', { token: first.token, data: { password, confirmation: false } });
    ok(reply.status === 422, 'A deletion request requires explicit confirmation');
    reply = await request('deletion_request', { token: first.token, data: { password, confirmation: true, reason: 'Isolated local account deletion fixture.' } });
    ok(reply.status === 202 && reply.body.status === 'pending' && /request/i.test(reply.body.message), 'Deletion records an honest pending request rather than claiming immediate financial-record removal');
    reply = await request('me', { token: first.token }); ok(reply.status === 401, 'Deletion request signs the student out of all mobile devices');
    const web = await fetch(`${base}/dashboard.php`, { headers: { Cookie: browserCookie }, redirect: 'manual' });
    ok(web.status === 303 && /login\.php/.test(web.headers.get('location')), 'Deletion request also invalidates the existing website session');

    for (let i = 0; i < 5; i++) { reply = await login(emails.limit, 'Wrong fixture password'); assert.equal(reply.status, 401); }
    reply = await login(emails.limit, 'Wrong fixture password');
    ok(reply.status === 429 && reply.headers.get('retry-after') === '900' && !reply.body.access_token, 'Repeated native login failures hit a persisted rate limit');
    ok(!/Fatal error|PHP Warning|Uncaught|Parse error/.test(stderr), 'The isolated mobile HTTP workflow finishes without PHP runtime errors');
    console.log(`PASS: ${checks} mobile API HTTP checks. Isolated database; no real provider calls or accounts.`);
})().catch(error => { console.error(error.message || String(error)); process.exitCode = 1; }).finally(async () => {
    if (server) { server.kill(); await new Promise(resolve => { server.once('exit', resolve); setTimeout(resolve, 1000); }); }
    if (created) { try { sql(`DROP DATABASE IF EXISTS \`${database}\`; DROP USER IF EXISTS '${databaseUser}'@'127.0.0.1';`); } catch (error) { console.error(error.message); process.exitCode = 1; } }
    fs.rmSync(scratch, { recursive: true, force: true });
});
