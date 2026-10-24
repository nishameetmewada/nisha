/* Real local owner/account/promotion workflows. No provider calls or real accounts. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const base = process.env.NISHA_TEST_BASE || 'http://127.0.0.1:8000';
const origin = new URL(base).origin;
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Admin fixtures require loopback development only.');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const suffix = crypto.randomBytes(8).toString('hex');
const password = crypto.randomBytes(24).toString('hex');
const replacementPassword = crypto.randomBytes(24).toString('hex');
const emails = Object.fromEntries(['owner', 'student', 'target'].map(role => [role, `admin-${role}-${suffix}@example.test`]));
const changedEmail = `admin-updated-${suffix}@example.test`;
const promoCode = `LOCAL${suffix.toUpperCase()}`;
let browser, fixture, checks = 0;
const contexts = [], errors = [], external = [];
const ok = (value, label) => { assert(value, label); checks++; console.log('PASS', label); };

function data(action, values = {}) {
    const script = `
ini_set('display_errors','0');
require 'includes/admin-accounts.php';
try {
 $v=json_decode($argv[2],true,32,JSON_THROW_ON_ERROR);$p=db();$out=[];
 if($argv[1]==='init') {
  $ids=[];foreach($v['emails'] as$role=>$email){$q=$p->prepare('INSERT INTO users(name,email,password_hash) VALUES (?,?,?)');$q->execute(['Admin fixture '.ucfirst($role),$email,password_hash($v['password'],PASSWORD_DEFAULT)]);$ids[$role]=(int)$p->lastInsertId();}
  $q=$p->prepare("INSERT INTO user_roles(user_id,role) VALUES (?,'admin')");$q->execute([$ids['owner']]);$out=['ids'=>$ids];
 } elseif($argv[1]==='user') {
  $q=$p->prepare('SELECT id,name,email FROM users WHERE id=?');$q->execute([$v['id']]);$out=$q->fetch()?:[];
  $q=$p->prepare("SELECT 1 FROM user_roles WHERE user_id=? AND role='admin'");$q->execute([$v['id']]);$out['admin']=(bool)$q->fetchColumn();
 } elseif($argv[1]==='safe_detail') {$out=nisha_admin_accounts_detail($p,(int)$v['id']);}
 elseif($argv[1]==='promotion') {
  $q=$p->prepare('SELECT id,code,enabled,discount_type,discount_value,currency FROM commerce_promotions WHERE code=?');$q->execute([$v['code']]);$out=$q->fetch()?:[];
 } elseif($argv[1]==='reservation') {
  $id=bin2hex(random_bytes(16));$q=$p->prepare("INSERT INTO payment_orders(id,user_id,amount_minor,currency,access_days,status) VALUES(?,?,?,?,?,'pending')");$q->execute([$id,$v['user_id'],$v['quote']['amount_minor'],$v['quote']['currency'],$v['quote']['access_days']]);
  require_once 'includes/promotions.php';$q=$v['quote'];$q['promotion_id']=$v['promotion_id'];nisha_promotions_snapshot($p,['id'=>$id,'user_id'=>$v['user_id']],$q);$out=['order_id'=>$id];
 }
 elseif($argv[1]==='cleanup') {
  $ids=array_map('intval',$v['ids']);
  if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$q=$p->prepare('DELETE FROM admin_account_audit WHERE actor_id IN ('.$marks.') OR target_id IN ('.$marks.')');$q->execute([...$ids,...$ids]);$q=$p->prepare('DELETE FROM users WHERE id IN ('.$marks.')');$q->execute($ids);}
  if($v['promoCode']){$q=$p->prepare('SELECT id FROM commerce_promotions WHERE code=?');$q->execute([$v['promoCode']]);$id=$q->fetchColumn();if($id){$q=$p->prepare('DELETE FROM commerce_promotions_audit WHERE promotion_id=?');$q->execute([$id]);$q=$p->prepare('DELETE FROM commerce_promotions WHERE id=?');$q->execute([$id]);}}
  $out=['cleaned'=>true];
 }
 session_write_close();echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable$e){fwrite(STDERR,'Local admin fixture '.$argv[1].' failed.');exit(1);}
`;
    try { return JSON.parse(execFileSync(php, ['-r', script, action, JSON.stringify(values)], { cwd: root, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] })); }
    catch (_) { throw new Error(`Local admin fixture ${action} failed; check the additive account/promotion migrations.`); }
}

async function actor(role, email = emails[role], secret = password) {
    const context = await browser.newContext(); contexts.push(context);
    await context.route('**/*', route => {
        const url = new URL(route.request().url());
        if (['http:', 'https:'].includes(url.protocol) && url.origin !== origin) { external.push(url.origin); return route.abort(); }
        return route.continue();
    });
    const page = await context.newPage(); page.on('pageerror', error => errors.push(error.message));
    await login(page, email, secret);
    return { page, context };
}

async function login(page, email, secret, accepted = true) {
    await page.goto(`${base}/login.php`);
    await page.locator('#email').fill(email); await page.locator('#password').fill(secret);
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.auth-card button[type=submit]').click()]);
    if (accepted) assert(new URL(page.url()).pathname === '/dashboard.php', 'The disposable actor must sign in.');
    else assert(new URL(page.url()).pathname === '/login.php', 'The unavailable account must remain signed out.');
}

async function accountChange(owner, targetId, action, values = {}) {
    await owner.page.goto(`${base}/admin-accounts.php?id=${targetId}`);
    const form = owner.page.locator('form').filter({ has: owner.page.locator(`[name=action][value="${action}"]`) }).first();
    await form.locator('[name=current_password]').fill(values.current_password || password);
    await form.locator('[name=reason]').fill(values.reason || `Local ${action} verification ${suffix}`);
    for (const [field, value] of Object.entries(values)) {
        if (!['current_password', 'reason'].includes(field)) await form.locator(`[name="${field}"]`).fill(value);
    }
    if (await form.locator('[name=confirmation]').count()) await form.locator('[name=confirmation]').check();
    const actionControl = form.locator(`[name=action][value="${action}"]`);
    const button = await actionControl.evaluate(node => node.tagName === 'BUTTON') ? actionControl : form.locator('button[type=submit],button:not([type])').first();
    await Promise.all([owner.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
    return owner.page.locator('main').innerText();
}

async function privateStatus(actor) {
    const response = await actor.context.request.get(`${base}/api/exam-result.php?id=1`);
    return response.status();
}

async function formPost(actor, file, fields) {
    const response = await actor.context.request.post(`${base}/${file}`, { form: fields, maxRedirects: 0 });
    return { status: response.status(), html: await response.text() };
}

async function csrf(page) { return page.locator('meta[name=csrf-token]').getAttribute('content'); }

async function quote(actor, payload, validCsrf = true) {
    return actor.page.evaluate(async ({ payload, validCsrf }) => {
        const response = await fetch('api/promotion-quote.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': validCsrf ? document.querySelector('meta[name=csrf-token]').content : 'invalid-csrf' }, body: JSON.stringify(payload) });
        return { status: response.status, body: await response.json() };
    }, { payload, validCsrf });
}

async function promotionSave(owner, values, id = 0) {
    await owner.page.goto(`${base}/admin-promotions.php${id ? `?id=${id}` : ''}`);
    const form = owner.page.locator('#promotion-form');
    for (const [field, value] of Object.entries(values)) {
        const input = form.locator(`[name="${field}"]`);
        if (['type', 'currency'].includes(field)) await input.selectOption(value);
        else if (['enabled', 'featured'].includes(field)) await input.setChecked(value);
        else await input.fill(value);
    }
    await Promise.all([owner.page.waitForNavigation({ waitUntil: 'domcontentloaded' }), form.locator('button[type=submit]').click()]);
}

(async () => {
    fixture = data('init', { emails, password });
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const owner = await actor('owner'), student = await actor('student'), target = await actor('target');
    for (const file of ['admin.php', 'admin-accounts.php', 'admin-promotions.php']) {
        const denied = await student.context.request.get(`${base}/${file}`);
        ok(denied.status() === 403 && !(await denied.text()).includes(emails.target), `A student cannot inspect ${file} or another account's details`);
    }
    const forged = await formPost(student, `admin-accounts.php?id=${fixture.ids.target}`, { csrf_token: await csrf(student.page), user_id: fixture.ids.target, action: 'grant_admin', current_password: password, reason: 'Unauthorised local role change' });
    ok(forged.status === 403 && !data('user', { id: fixture.ids.target }).admin, 'A non-owner cannot grant roles by submitting the owner form directly');
    await owner.page.goto(`${base}/admin-accounts.php?q=${encodeURIComponent(emails.target)}`);
    ok((await owner.page.locator('main').innerText()).includes(emails.target) && !(await owner.page.content()).includes('$2y$'), 'The owner can search accounts without receiving password hashes');
    const rejected = await formPost(owner, `admin-accounts.php?id=${fixture.ids.target}`, { csrf_token: 'invalid-csrf', user_id: fixture.ids.target, action: 'edit_profile', current_password: password, reason: 'CSRF regression fixture', name: 'Forged account name', email: emails.target });
    ok(data('user', { id: fixture.ids.target }).name === 'Admin fixture Target' && /session expired|refresh/i.test(rejected.html), 'Account editing rejects an invalid CSRF token before mutation');
    await accountChange(owner, fixture.ids.target, 'edit_profile', { name: 'Wrong password edit', email: emails.target, current_password: 'wrong-current-password' });
    ok(data('user', { id: fixture.ids.target }).name === 'Admin fixture Target', 'Account editing requires the acting owner’s correct current password');
    await accountChange(owner, fixture.ids.target, 'edit_profile', { name: 'Updated Local Student', email: changedEmail });
    ok(data('user', { id: fixture.ids.target }).name === 'Updated Local Student' && data('user', { id: fixture.ids.target }).email === changedEmail, 'The owner can update a real student name and email');
    ok(await privateStatus(target) === 401, 'Changing an account email revokes its existing authenticated cookie');
    await login(target.page, changedEmail, password);
    await accountChange(owner, fixture.ids.target, 'reset_password', { new_password: replacementPassword, password_confirmation: replacementPassword });
    ok(await privateStatus(target) === 401, 'An owner password reset revokes all prior sessions');
    await login(target.page, changedEmail, password, false);
    await login(target.page, changedEmail, replacementPassword);
    ok(new URL(target.page.url()).pathname === '/dashboard.php', 'Only the replacement password can sign in after the reset');
    await accountChange(owner, fixture.ids.target, 'suspend');
    ok(await privateStatus(target) === 401, 'Suspending a student immediately removes private API access from an existing cookie');
    await login(target.page, changedEmail, replacementPassword, false);
    ok(await target.page.locator('.form-error').isVisible(), 'A suspended account cannot create a new authenticated session');
    await accountChange(owner, fixture.ids.target, 'restore');
    await login(target.page, changedEmail, replacementPassword);
    ok(new URL(target.page.url()).pathname === '/dashboard.php', 'An explicitly restored account can sign in again');
    await accountChange(owner, fixture.ids.target, 'grant_admin');
    ok(data('user', { id: fixture.ids.target }).admin && await privateStatus(target) === 401, 'Granting owner access is audited separately and requires a fresh sign-in');
    await login(target.page, changedEmail, replacementPassword);
    ok((await target.context.request.get(`${base}/admin-accounts.php`)).status() === 200, 'The newly assigned owner can open account controls after signing in');
    await accountChange(owner, fixture.ids.target, 'revoke_admin');
    ok(!data('user', { id: fixture.ids.target }).admin && await privateStatus(target) === 401, 'Removing owner access revokes the promoted owner’s existing session');
    await login(target.page, changedEmail, replacementPassword);
    ok((await target.context.request.get(`${base}/admin-accounts.php`)).status() === 403, 'A demoted account cannot reopen owner controls with a fresh session');
    // Backend guards apply even when the UI intentionally omits self-lockout buttons.
    await owner.page.goto(`${base}/admin-accounts.php?id=${fixture.ids.owner}`);
    for (const action of ['suspend', 'revoke_admin']) {
        await formPost(owner, `admin-accounts.php?id=${fixture.ids.owner}`, { csrf_token: await csrf(owner.page), user_id: fixture.ids.owner, action, confirmation: '1', current_password: password, reason: 'Self-lockout regression fixture' });
        ok(data('user', { id: fixture.ids.owner }).admin && (await owner.context.request.get(`${base}/admin-accounts.php`)).status() === 200, `The owner cannot ${action} their own account through a forged form`);
    }
    const safe = JSON.stringify(data('safe_detail', { id: fixture.ids.target }));
    ok(!/password_hash|\$2y\$|\$argon2/.test(safe) && !safe.includes(password) && !safe.includes(replacementPassword), 'Account detail and audit DTOs exclude password hashes and plaintext passwords');
    await student.page.goto(`${base}/pricing.php`);
    const standardQuote = await quote(student, { promotion_code: '' });
    assert(standardQuote.status === 200, 'The standard server price must be readable.');
    const baseQuote = standardQuote.body.quote;
    const offer = { code: promoCode, label: `Local private discount ${suffix}`, type: 'percent', percent: '25', fixed_amount: '', currency: baseQuote.currency, starts_at: '', ends_at: '', max_redemptions: '2', max_per_student: '1', enabled: true, featured: false };
    const promoFields = Object.fromEntries(Object.entries(offer).map(([key, value]) => [key, typeof value === 'boolean' ? value ? '1' : '0' : value]));
    let response = await formPost(student, 'admin-promotions.php', { ...promoFields, csrf_token: await csrf(student.page), action: 'save', id: '0' });
    ok(response.status === 403 && !data('promotion', { code: promoCode }).id, 'A student cannot create a discount through a forged owner form');
    await owner.page.goto(`${base}/admin-promotions.php`);
    response = await formPost(owner, 'admin-promotions.php', { ...promoFields, csrf_token: 'invalid-csrf', action: 'save', id: '0' });
    ok(response.status === 403 && !data('promotion', { code: promoCode }).id, 'Promotion writes require a valid owner CSRF token');
    await promotionSave(owner, offer);
    const promotion = data('promotion', { code: promoCode });
    ok(promotion.id && promotion.enabled && promotion.discount_type === 'percent' && Number(promotion.discount_value) === 25, 'The owner creates a real, non-featured promotion through the browser');
    let result = await quote(student, { promotion_code: promoCode.toLowerCase() });
    const discount = Math.floor(baseQuote.base_amount_minor / 4);
    ok(result.status === 200 && result.body.quote.code === promoCode && result.body.quote.discount_minor === discount && result.body.quote.amount_minor === baseQuote.base_amount_minor - discount && !('promotion_id' in result.body.quote), 'A student receives the server-computed 25% discount without internal promotion identifiers');
    await student.page.locator('#promotion-code').fill(promoCode);
    await student.page.locator('#apply-promotion').click();
    await student.page.waitForFunction(() => /Estimated total:/i.test(document.querySelector('#promotion-quote').textContent));
    ok((await student.page.locator('#promotion-quote').innerText()).includes(result.body.quote.formatted_total), 'The actual pricing interface displays the quoted discounted total');
    result = await quote(student, { promotion_code: promoCode }, false);
    ok(result.status === 403, 'A forged quote request cannot bypass CSRF protection');
    result = await quote(student, { promotion_code: promoCode, amount_minor: 1 });
    ok(result.status === 422 && !result.body.quote, 'The quote endpoint rejects a browser-supplied price override');
    response = await formPost(student, 'api/checkout.php', { csrf_token: await csrf(student.page), promotion_code: 'INVALID!CODE' });
    const nativeError = JSON.parse(response.html);
    ok(response.status === 422 && nativeError.code === 'promotion_invalid', 'Native checkout accepts its CSRF form field before safely rejecting an invalid code without calling Stripe');
    result = await quote(student, { promotion_code: 'MISSING' + suffix });
    ok(result.status === 422 && result.body.code === 'promotion_unavailable', 'An unknown discount cannot change the current price');
    await promotionSave(owner, { ends_at: '2021-01-01T00:00' }, promotion.id);
    result = await quote(student, { promotion_code: promoCode });
    ok(result.status === 422 && result.body.code === 'promotion_unavailable', 'A promotion with a past UTC end cannot be applied');
    await promotionSave(owner, { starts_at: '2090-01-01T00:00', ends_at: '2091-01-01T00:00' }, promotion.id);
    result = await quote(student, { promotion_code: promoCode });
    ok(result.status === 422 && result.body.code === 'promotion_unavailable', 'A promotion cannot be applied before its UTC start');
    await promotionSave(owner, { type: 'fixed', fixed_amount: '5.00', starts_at: '', ends_at: '', currency: baseQuote.currency === 'usd' ? 'aud' : 'usd' }, promotion.id);
    result = await quote(student, { promotion_code: promoCode });
    ok(result.status === 422 && result.body.code === 'promotion_unavailable', 'Fixed discounts cannot be applied to a different plan currency');
    await promotionSave(owner, offer, promotion.id);
    await owner.page.goto(`${base}/admin-promotions.php?id=${promotion.id}`);
    response = await formPost(owner, 'admin-promotions.php', { action: 'toggle', id: String(promotion.id), enabled: '0', csrf_token: await csrf(owner.page) });
    result = await quote(student, { promotion_code: promoCode });
    ok(response.status === 303 && result.status === 422 && !data('promotion', { code: promoCode }).enabled, 'Disabling a code immediately prevents new discounted quotations');
    await promotionSave(owner, { enabled: true }, promotion.id);
    await target.page.goto(`${base}/pricing.php`);
    result = await quote(target, { promotion_code: promoCode });
    assert(result.status === 200, 'The fixture promotion must be available before a synthetic reservation.');
    data('reservation', { user_id: fixture.ids.target, promotion_id: promotion.id, quote: result.body.quote });
    result = await quote(target, { promotion_code: promoCode });
    ok(result.status === 409 && result.body.code === 'promotion_limit', 'A pending checkout reservation consumes that student’s redemption allowance');
    result = await quote(student, { promotion_code: promoCode });
    ok(result.status === 200, 'A different eligible student can still use the remaining promotion capacity');
    data('reservation', { user_id: fixture.ids.student, promotion_id: promotion.id, quote: result.body.quote });
    await owner.page.goto(`${base}/pricing.php`);
    result = await quote(owner, { promotion_code: promoCode });
    ok(result.status === 409 && result.body.code === 'promotion_limit', 'Global promotion capacity includes reservations across different students');
    ok(errors.length === 0 && external.length === 0, 'Owner account workflows finish without browser errors or external requests');
    console.log(`PASS: ${checks} owner/account/promotion browser checks. No external provider calls.`);
})().catch(error => { console.error(error.stack); process.exitCode = 1; }).finally(async () => {
    for (const context of contexts) await context.close();
    if (browser) await browser.close();
    try { if (fixture?.ids) data('cleanup', { ids: Object.values(fixture.ids), promoCode }); }
    catch (_) { console.error('Local admin fixture cleanup failed.'); process.exitCode = 1; }
});
