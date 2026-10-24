/* Real local HTTP webhook/auth checks. Fake signing keys, no provider calls. */
'use strict';
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const { execFileSync, spawn } = require('node:child_process');
const net = require('node:net');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const secret = 'whsec_nisha_http_fixture';
const email = `commerce-http-${crypto.randomUUID()}@example.test`;
const password = crypto.randomBytes(20).toString('hex');
let server, fixture, base, cookie = '', checks = 0;
const eventIds = [];
function ok(value, label) { assert(value, label); checks++; console.log('PASS', label); }
function localPhp(source, value) {
    return JSON.parse(execFileSync(php, ['-r', `ini_set('display_errors','0');try{require 'includes/commerce.php';$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);${source}}catch(Throwable $e){fwrite(STDERR,"Local commerce fixture unavailable.\n");exit(1);}`], { cwd: root, input: JSON.stringify(value), encoding: 'utf8' }));
}
async function request(route, options = {}, authenticated = false) {
    const headers = { ...(options.headers || {}) };
    if (authenticated && cookie) headers.Cookie = cookie;
    const result = await fetch(base + route, { ...options, headers, redirect: 'manual' });
    if (authenticated) {
        const cookies = result.headers.getSetCookie ? result.headers.getSetCookie() : [result.headers.get('set-cookie') || ''];
        for (const value of cookies) { const match = value.match(/(?:^|,\s*)(nisha_session=[^;]+)/); if (match) cookie = match[1]; }
    }
    return result;
}
function signed(raw, timestamp = Math.floor(Date.now() / 1000)) {
    return `t=${timestamp},v1=${crypto.createHmac('sha256', secret).update(`${timestamp}.${raw}`).digest('hex')}`;
}
async function webhook(event, signature) {
    const raw = typeof event === 'string' ? event : JSON.stringify(event);
    const result = await request('/api/stripe-webhook.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Stripe-Signature': signature === undefined ? signed(raw) : signature }, body: raw });
    return { status: result.status, data: await result.json() };
}
(async () => {
    fixture = localPhp(`
$q=db()->prepare('INSERT INTO users(name,email,password_hash) VALUES (?,?,?)');$q->execute(['Commerce HTTP fixture',$input['email'],password_hash($input['password'],PASSWORD_DEFAULT)]);$user=(int)db()->lastInsertId();
$id=bin2hex(random_bytes(16));$checkout='cs_test_http_'.$id;
$q=db()->prepare("INSERT INTO payment_orders(id,user_id,amount_minor,currency,access_days,checkout_id) VALUES (?,?,2900,'aud',30,?)");$q->execute([$id,$user,$checkout]);
echo json_encode(['user_id'=>$user,'order_id'=>$id,'checkout_id'=>$checkout]);`, { email, password });
    const probe = net.createServer();
    await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
    const port = probe.address().port; await new Promise(resolve => probe.close(resolve));
    base = `http://127.0.0.1:${port}`;
    server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', root], { cwd: root, env: { ...process.env,
        NISHA_SITE_URL: 'https://nisha.example', NISHA_STRIPE_SECRET_KEY: 'sk_test_nisha_http_fixture',
        NISHA_STRIPE_WEBHOOK_SECRET: secret, NISHA_PAYMENTS_ENABLED: '0', NISHA_PAYMENT_AMOUNT_MINOR: '2900', NISHA_PAYMENT_CURRENCY: 'aud', NISHA_PAYMENT_ACCESS_DAYS: '30' }, stdio: ['ignore','ignore','pipe'] });
    let errors = ''; server.stderr.on('data', chunk => { errors += chunk; });
    let ready = false;
    for (let attempt = 0; attempt < 40; attempt++) {
        try { const response = await request('/pricing.php'); if (response.status === 200) { ready = true; break; } } catch (_) {}
        await new Promise(resolve => setTimeout(resolve, 50));
    }
    assert(ready, 'The isolated loopback HTTP server must start.');
    let result = await request('/api/stripe-webhook.php');
    ok(result.status === 405 && result.headers.get('allow') === 'POST', 'Webhook requires the documented HTTP method.');
    result = await request('/api/checkout.php', { method: 'POST' });
    ok(result.status === 401, 'Checkout requires a signed-in student.');
    let reply = await webhook('{}', '');
    ok(reply.status === 400 && reply.data.code === 'webhook_signature', 'Unsigned HTTP payment events are rejected.');
    reply = await webhook('{');
    ok(reply.status === 400 && reply.data.code === 'webhook_event', 'Signed invalid JSON cannot become a payment event.');
    const event = { id: `evt_http_${fixture.order_id}`, type: 'checkout.session.completed', livemode: false, data: { object: {
        mode: 'payment', status: 'complete', payment_status: 'paid', id: fixture.checkout_id,
        client_reference_id: fixture.order_id, metadata: { order_id: fixture.order_id, user_id: String(fixture.user_id) },
        amount_total: 2900, currency: 'aud', payment_intent: `pi_http_${fixture.order_id}` } } };
    eventIds.push(event.id);
    const raw = JSON.stringify(event);
    reply = await webhook(raw + ' ', signed(raw));
    ok(reply.status === 400, 'HTTP raw-body modification fails the signature check.');
    reply = await webhook(raw, signed(raw, Math.floor(Date.now() / 1000) - 301));
    ok(reply.status === 400, 'Expired HTTP signatures are rejected.');
    const mismatch = structuredClone(event); mismatch.livemode = true;
    reply = await webhook(mismatch);
    ok(reply.status === 400 && reply.data.code === 'webhook_mode', 'Live-mode events cannot be accepted in test mode.');
    result = await request('/login.php', {}, true);
    let html = await result.text();
    let csrf = html.match(/name="csrf_token" value="([a-f0-9]{64})"/)[1];
    result = await request('/login.php', { method: 'POST', body: new URLSearchParams({ email, password, csrf_token: csrf }) }, true);
    ok(result.status === 303, 'The disposable student signs in through the real login form.');
    result = await request('/pricing.php', {}, true); html = await result.text();
    csrf = html.match(/name="csrf-token" content="([a-f0-9]{64})"/)[1];
    result = await request('/api/checkout.php', { method: 'POST', headers: { Accept: 'application/json' } }, true);
    ok(result.status === 403, 'Authenticated checkout still requires CSRF protection.');
    result = await request('/api/checkout.php', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf } }, true);
    const disabled = await result.json();
    ok(result.status === 503 && disabled.code === 'payments_not_configured', 'Paused checkout returns before any provider connection.');
    reply = await webhook(event);
    ok(reply.status === 200 && reply.data.received === true, 'A correctly bound signed HTTP event activates access while new checkout is paused.');
    reply = await webhook(event);
    ok(reply.status === 200 && reply.data.duplicate === true, 'HTTP webhook replay does not duplicate access.');
    const wrongAmount = structuredClone(event); wrongAmount.id += '_wrong'; wrongAmount.data.object.amount_total = 1;
    reply = await webhook(wrongAmount);
    ok(reply.status === 400 && reply.data.code === 'webhook_binding', 'A signed HTTP event cannot alter the agreed price.');
    result = await request('/pricing.php', {}, true); html = await result.text();
    ok(html.includes('Your pass is active until') && !html.includes(secret) && !html.includes('sk_test_nisha_http_fixture'), 'The real pricing page reflects paid access without exposing connection secrets.');
    const refund = { id: `evt_http_refund_${fixture.order_id}`, type: 'charge.refunded', livemode: false, data: { object: {
        payment_intent: event.data.object.payment_intent, currency: 'aud', amount_refunded: 2900,
        refunds: { data: [{ id: `re_http_${fixture.order_id}`, status: 'succeeded', amount: 2900, currency: 'aud' }] } } } };
    eventIds.push(refund.id);
    reply = await webhook(refund);
    ok(reply.status === 200, 'A signed HTTP refund is processed while new checkout remains paused.');
    result = await request('/pricing.php', {}, true); html = await result.text();
    ok(!html.includes('Your pass is active until'), 'Refunded access disappears from the real student pricing page.');
    reply = await webhook(refund);
    ok(reply.data.duplicate === true, 'Replayed HTTP refunds are idempotent.');
    ok(!/Fatal error|Uncaught|PHP Warning/.test(errors), 'The complete HTTP flow finishes without PHP runtime errors.');
    console.log(`PASS: ${checks} commerce HTTP/auth/webhook checks. Fake local signatures; no provider calls.`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (server) { server.kill(); await new Promise(resolve => { server.once('exit', resolve); setTimeout(resolve, 1000); }); }
    if (fixture) localPhp(`
foreach($input['events'] as $id){$q=db()->prepare('DELETE FROM payment_events WHERE event_id=?');$q->execute([$id]);}
$q=db()->prepare('DELETE FROM users WHERE id=? AND email=?');$q->execute([$input['user_id'],$input['email']]);echo json_encode(['cleaned'=>true]);`, { user_id: fixture.user_id, email, events: eventIds });
});
