/* Local teacher/owner/student integration. No real payments or external media. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const base = process.env.NISHA_TEST_BASE || 'http://127.0.0.1:8000';
const origin = new URL(base).origin;
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Use loopback development only.');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const suffix = crypto.randomUUID();
const password = crypto.randomBytes(24).toString('hex');
const emails = Object.fromEntries(['owner', 'teacher', 'student'].map(role => [role, `platform-${role}-${suffix}@example.test`]));
const videoUrl = `https://media.example.org/nisha-${suffix}.mp4`;
const title = `Local teacher lesson ${suffix}`;
let browser, fixture, policyTouched = false, checks = 0;
const contexts = [], errors = [], external = [];
const ok = (value, message) => { assert(value, message); checks++; console.log('PASS', message); };

function data(action, values = {}) {
    const script = `
require 'includes/teachers.php';
try {
 $v=json_decode($argv[2],true,32,JSON_THROW_ON_ERROR); $p=db(); $out=[];
 if($argv[1]==='init') {
  $ids=[];
  foreach($v['emails'] as $role=>$email) { $q=$p->prepare('INSERT INTO users(name,email,password_hash) VALUES (?,?,?)'); $q->execute(['Platform '.ucfirst($role),$email,password_hash($v['password'],PASSWORD_DEFAULT)]); $ids[$role]=(int)$p->lastInsertId(); }
  $q=$p->prepare('INSERT INTO user_roles(user_id,role) VALUES (?,?)'); $q->execute([$ids['owner'],'admin']);
  $out=['ids'=>$ids,'policy'=>$p->query('SELECT * FROM teacher_policy_settings WHERE id=1')->fetch()?:null];
 } elseif($argv[1]==='teacher') {
  $q=$p->prepare('SELECT status FROM teacher_profiles WHERE user_id=?'); $q->execute([$v['id']]); $out=['status'=>$q->fetchColumn()?:null];
 } elseif($argv[1]==='video') {
  $q=$p->prepare('SELECT id,status,revision FROM coaching_videos WHERE teacher_id=? AND title=? ORDER BY id DESC LIMIT 1'); $q->execute([$v['id'],$v['title']]); $out=$q->fetch()?:[];
 } elseif($argv[1]==='paid') {
  $q=$p->prepare("INSERT INTO payment_orders(id,user_id,amount_minor,currency,access_days,status,paid_at) VALUES (?,?,2900,'aud',30,'paid',UTC_TIMESTAMP())"); $q->execute([$v['order'],$v['id']]);
  $q=$p->prepare('INSERT INTO student_entitlements(user_id,order_id,starts_at,expires_at) VALUES (?,?,UTC_TIMESTAMP()-INTERVAL 1 DAY,UTC_TIMESTAMP()+INTERVAL 30 DAY)'); $q->execute([$v['id'],$v['order']]); $out=['paid'=>true];
 } elseif($argv[1]==='policy') { $s=nisha_teacher_settings(); $out=['share'=>$s['share_percent'],'enabled'=>$s['enabled'],'hosts'=>$s['video_hosts']];
 } elseif($argv[1]==='cleanup') {
  if($v['touched'] && $v['owner']) {
   $q=$p->query('SELECT updated_by FROM teacher_policy_settings WHERE id=1'); $current=$q->fetchColumn();
   if((int)$current===(int)$v['owner']) {
    if($v['policy']) { $old=$v['policy']; $q=$p->prepare('UPDATE teacher_policy_settings SET enabled=?,watch_percent=?,paid_students_only=?,currency=?,video_hosts_json=?,updated_by=?,updated_at=? WHERE id=1 AND updated_by=?'); $q->execute([$old['enabled'],$old['watch_percent'],$old['paid_students_only'],$old['currency'],$old['video_hosts_json'],$old['updated_by'],$old['updated_at'],$v['owner']]); }
    else { $q=$p->prepare('DELETE FROM teacher_policy_settings WHERE id=1 AND updated_by=?'); $q->execute([$v['owner']]); }
   }
  }
  $q=$p->prepare('DELETE FROM users WHERE email IN (?,?,?)'); $q->execute(array_values($v['emails'])); $out=['cleaned'=>true];
 }
 session_write_close(); echo json_encode($out,JSON_THROW_ON_ERROR);
} catch(Throwable $e) { fwrite(STDERR,'Local platform fixture operation failed.'); exit(1); }
`;
    try { return JSON.parse(execFileSync(php, ['-r', script, action, JSON.stringify(values)], { cwd: root, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] })); }
    catch (_) { throw new Error(`Platform fixture ${action} failed; check the local schema/setup.`); }
}

async function actor(role) {
    const context = await browser.newContext(); contexts.push(context);
    await context.route('**/*', route => {
        const url = new URL(route.request().url());
        if (url.origin === origin) return route.continue();
        if (url.href === videoUrl) return route.fulfill({ status: 200, contentType: 'video/mp4', body: '' });
        if (['http:', 'https:'].includes(url.protocol)) { external.push(url.origin); return route.abort(); }
        return route.continue();
    });
    const page = await context.newPage(); page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${base}/login.php`);
    await page.locator('#email').fill(emails[role]); await page.locator('#password').fill(password);
    await Promise.all([page.waitForURL('**/dashboard.php'), page.locator('.auth-card button[type=submit]').click()]);
    return { page, context };
}

async function formSubmit(page, form, action) {
    const actionControl = form.locator(`[name=action][value="${action}"]`);
    const button = await actionControl.count() && await actionControl.evaluate(node => node.tagName === 'BUTTON') ? actionControl : form.locator('button[type=submit],button:not([type])').first();
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
}

async function progress(page, payload, validToken = true) {
    return page.evaluate(async ({ payload, validToken }) => {
        const result = await fetch('api/video-progress.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': validToken ? document.querySelector('meta[name=csrf-token]').content : 'invalid-csrf' }, body: JSON.stringify(payload) });
        return { status: result.status, body: await result.json() };
    }, { payload, validToken });
}

(async () => {
    fixture = data('init', { emails, password });
    browser = await chromium.launch({ executablePath: process.env.NISHA_CHROMIUM_BIN || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
    const teacher = await actor('teacher'), owner = await actor('owner'), student = await actor('student');
    const denied = await teacher.context.request.get(`${base}/admin.php`);
    ok(denied.status() === 403, 'Teacher applicants cannot open owner moderation controls');

    await teacher.page.goto(`${base}/teacher-portal.php`);
    let form = teacher.page.locator('form').filter({ has: teacher.page.locator('[name=action][value=apply]') });
    await form.locator('[name=display_name]').fill(`Platform Teacher ${suffix}`);
    await form.locator('[name=bio]').fill('Disposable local integration fixture for IELTS and PTE teaching experience.');
    await formSubmit(teacher.page, form, 'apply');
    ok(data('teacher', { id: fixture.ids.teacher }).status === 'pending', 'A teacher application is genuinely saved as pending approval');
    ok(await teacher.page.locator('[name=action][value=submit_video]').count() === 0, 'Unapproved teachers cannot submit a coaching lesson');

    await owner.page.goto(`${base}/admin.php`);
    form = owner.page.locator('form').filter({ has: owner.page.locator('[name=action][value=save_policy]') });
    await form.locator('[name=video_hosts]').fill('media.example.org');
    await form.locator('[name=watch_percent]').fill('70');
    await form.locator('[name=currency]').selectOption('AUD');
    const paidOnly = form.locator('[name=paid_students_only]'); if (await paidOnly.count()) await paidOnly.check();
    const enabled = form.locator('[name=enabled]'); if (await enabled.count()) await enabled.uncheck();
    policyTouched = true; await formSubmit(owner.page, form, 'save_policy');
    ok(data('policy').share === 90, 'Saving owner video policy preserves the required 90% net-profit reward share');
    form = owner.page.locator('form').filter({ has: owner.page.locator('[name=action][value=approve_teacher]') }).filter({ has: owner.page.locator(`[name=user_id][value="${fixture.ids.teacher}"]`) });
    await formSubmit(owner.page, form, 'approve_teacher');
    ok(data('teacher', { id: fixture.ids.teacher }).status === 'approved', 'Only the owner approval action opens the teacher’s lesson-submission role');

    await teacher.page.goto(`${base}/teacher-portal.php`);
    form = teacher.page.locator('form').filter({ has: teacher.page.locator('[name=action][value=submit_video]') });
    await form.locator('[name=title]').fill(title);
    await form.locator('[name=description]').fill('A local integration lesson explaining how to structure an IELTS response with clear supporting examples.');
    await form.locator('[name=video_url]').fill(videoUrl);
    await form.locator('[name=duration_seconds]').fill('120');
    await form.locator('[name=course_id]').selectOption('ielts-academic'); await form.locator('[name=skill]').selectOption('writing');
    await formSubmit(teacher.page, form, 'submit_video');
    const video = data('video', { id: fixture.ids.teacher, title });
    ok(video.id > 0 && video.status === 'pending', 'Approved teachers submit a real lesson into moderation, not directly into publication');
    await student.page.goto(`${base}/coaching.php`);
    ok(!(await student.page.locator('main').innerText()).includes(title), 'A pending lesson is absent from the student coaching catalogue');

    await owner.page.goto(`${base}/admin.php`);
    form = owner.page.locator('form').filter({ has: owner.page.locator('[name=action][value=approve_video]') }).filter({ has: owner.page.locator(`[name=video_id][value="${video.id}"]`) });
    const previewRevision = await form.locator('input[type=hidden]').evaluateAll(inputs => inputs.find(input => /revision/.test(input.name))?.value || null);
    ok(Number(previewRevision) === Number(video.revision), 'The owner moderation form binds its approval to the exact lesson revision shown');
    await teacher.page.goto(`${base}/teacher-portal.php?edit=${video.id}`);
    const editForm = teacher.page.locator('form').filter({ has: teacher.page.locator('[name=action][value=submit_video]') });
    await editForm.locator('[name=description]').fill('Updated local fixture: teach students to connect an IELTS argument with specific relevant evidence and a clear conclusion.');
    await formSubmit(teacher.page, editForm, 'submit_video');
    await form.locator('[name=verified_duration]').check();
    await formSubmit(owner.page, form, 'approve_video');
    const changedVideo = data('video', { id: fixture.ids.teacher, title });
    ok(changedVideo.status === 'pending' && Number(changedVideo.revision) > Number(previewRevision) && /changed|revision|reload|version/i.test(await owner.page.locator('main').innerText()), 'A teacher edit after the owner’s preview invalidates that stale approval and keeps the new lesson pending');
    await owner.page.reload();
    form = owner.page.locator('form').filter({ has: owner.page.locator('[name=action][value=approve_video]') }).filter({ has: owner.page.locator(`[name=video_id][value="${video.id}"]`) });
    await form.locator('[name=verified_duration]').check();
    await formSubmit(owner.page, form, 'approve_video');
    ok(data('video', { id: fixture.ids.teacher, title }).status === 'published', 'The owner can publish a reviewed lesson after its content/duration attestation');
    await student.page.goto(`${base}/coaching.php`);
    ok((await student.page.locator('main').innerText()).includes(title) && !(await student.page.content()).includes(videoUrl) && await student.page.locator(`#coaching-video-${video.id}`).count() === 0, 'A free student sees lesson information without receiving the paid video source');

    data('paid', { id: fixture.ids.student, order: crypto.randomBytes(16).toString('hex') });
    await student.page.reload();
    ok(await student.page.locator(`#coaching-video-${video.id} source`).getAttribute('src') === videoUrl, 'An active paid student receives the approved video player');
    const badCsrf = await progress(student.page, { action: 'start', video_id: Number(video.id) }, false);
    ok(badCsrf.status === 403, 'Video progress rejects a forged CSRF request');
    const started = await progress(student.page, { action: 'start', video_id: Number(video.id) });
    ok(started.status === 200 && started.body.reward_eligible === true && started.body.qualified === false && /^[a-f0-9]{64}$/.test(started.body.token), 'A paid student starts a bound watch session without earning an instant qualified view');
    const skipped = await progress(student.page, { action: 'heartbeat', video_id: Number(video.id), token: started.body.token, position: 119, playing: true, visible: true });
    ok(skipped.status === 200 && skipped.body.watched_seconds === 0 && skipped.body.qualified === false, 'An immediate seek cannot create watched time or a teacher reward view');
    await teacher.page.goto(`${base}/coaching.php`);
    const own = await progress(teacher.page, { action: 'start', video_id: Number(video.id) });
    ok(own.status === 200 && own.body.reward_eligible === false && own.body.qualified === false, 'A teacher’s own preview does not qualify as a rewarded student view');
    ok(errors.length === 0 && external.length === 0, 'Teacher, owner and student workflows run without JavaScript failures or external requests');
    console.log(`Platform browser checks passed: ${checks}; real UI moderation and local HTTP progress, no paid calls.`);
})().catch(error => { console.error(error.stack); process.exitCode = 1; }).finally(async () => {
    for (const context of contexts) await context.close();
    if (browser) await browser.close();
    try { data('cleanup', { emails, owner: fixture?.ids?.owner || null, policy: fixture?.policy || null, touched: policyTouched }); }
    catch (_) { console.error('Platform fixture cleanup failed.'); process.exitCode = 1; }
});
