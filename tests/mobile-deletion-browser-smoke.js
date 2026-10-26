/* Real local deletion UI. Exact disposable accounts, no remote services. */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const base = process.env.NISHA_TEST_BASE || 'http://127.0.0.1:8000';
const origin = new URL(base).origin;
if (!['127.0.0.1','localhost'].includes(new URL(base).hostname)) throw new Error('Deletion fixtures require loopback development only.');
const php = process.env.NISHA_PHP_BIN || '/workspace/.tools/bin/php';
const suffix = crypto.randomBytes(10).toString('hex');
const password = crypto.randomBytes(24).toString('hex');
const emails = { owner:`delete-owner-${suffix}@example.test`, student:`delete-student-${suffix}@example.test` };
let fixture, browser, checks=0;
const contexts=[],errors=[],external=[];
const ok=(condition,label)=>{assert(condition,label);checks++;console.log('PASS',label);};
function sql(action, input={}) {
    const source=`
ini_set('display_errors','0');require 'includes/mobile-deletion.php';
try{$v=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$p=db();$out=[];
if($argv[1]==='init'){$ids=[];foreach($v['emails']as$role=>$email){$q=$p->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$q->execute(['Deletion browser '.$role,$email,password_hash($v['password'],PASSWORD_DEFAULT)]);$ids[$role]=(int)$p->lastInsertId();}$p->prepare("INSERT INTO user_roles(user_id,role) VALUES(?,'admin')")->execute([$ids['owner']]);$out=$ids;}
elseif($argv[1]==='request'){$q=$p->prepare('SELECT status,reason,resolved_at FROM mobile_deletion_requests WHERE user_id=?');$q->execute([$v['id']]);$out=$q->fetch()?:[];}
elseif($argv[1]==='account'){$q=$p->prepare('SELECT id,name,email FROM users WHERE id=?');$q->execute([$v['id']]);$out=$q->fetch()?:[];}
elseif($argv[1]==='cleanup'){$ids=array_map('intval',$v['ids']);$marks=implode(',',array_fill(0,count($ids),'?'));$q=$p->prepare('DELETE FROM admin_account_audit WHERE actor_id IN ('.$marks.') OR target_id IN ('.$marks.')');$q->execute([...$ids,...$ids]);$q=$p->prepare('DELETE FROM mobile_deletion_requests WHERE user_id IN ('.$marks.')');$q->execute($ids);$q=$p->prepare('DELETE FROM users WHERE id IN ('.$marks.')');$q->execute($ids);$out=['cleaned'=>true];}
session_write_close();echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable$e){fwrite(STDERR,'Deletion browser fixture failed.');exit(1);}`;
    return JSON.parse(execFileSync(php,['-r',source,action],{cwd:root,input:JSON.stringify(input),encoding:'utf8',stdio:['pipe','pipe','pipe']}));
}
async function actor(role) {
    const context=await browser.newContext();contexts.push(context);
    await context.route('**/*',route=>{const url=new URL(route.request().url());if(['http:','https:'].includes(url.protocol)&&url.origin!==origin){external.push(url.origin);return route.abort();}return route.continue();});
    const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
    if(role){await page.goto(`${base}/login.php`);await page.locator('#email').fill(emails[role]);await page.locator('#password').fill(password);await Promise.all([page.waitForURL('**/dashboard.php'),page.locator('.auth-card button[type=submit]').click()]);}
    return{context,page};
}
async function token(page){return page.locator('meta[name=csrf-token]').getAttribute('content');}
async function post(context, route, form) {const response=await context.request.post(`${base}/${route}`,{form,maxRedirects:0});return{status:response.status(),html:await response.text()};}
(async()=>{
    fixture=sql('init',{emails,password});
    browser=await chromium.launch({executablePath:process.env.NISHA_CHROMIUM_BIN||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
    const guest=await actor();await guest.page.goto(`${base}/delete-account.php`);
    ok(new URL(guest.page.url()).pathname==='/login.php','The public deletion URL asks an anonymous visitor to sign in.');
    const student=await actor('student'),owner=await actor('owner');
    let response=await student.context.request.get(`${base}/admin-deletion.php`);
    ok(response.status()===403&&!(await response.text()).includes(emails.owner),'A student cannot inspect the owner deletion queue.');
    await student.page.goto(`${base}/delete-account.php`);
    let reply=await post(student.context,'delete-account.php',{csrf_token:'wrong',password,confirmation:'1',reason:'Browser privacy fixture'});
    ok(reply.status===403&&!sql('request',{id:fixture.student}).status,'The public request rejects forged CSRF before writing a queue entry.');
    reply=await post(student.context,'delete-account.php',{csrf_token:await token(student.page),password:'wrong-password',confirmation:'1'});
    ok(reply.status===401&&!sql('request',{id:fixture.student}).status&&!reply.html.includes('wrong-password'),'The request requires the account password and never echoes it.');
    for(const theme of['light','dark'])for(const width of[320,390,1440]){
        await student.page.setViewportSize({width,height:900});await student.page.evaluate(value=>localStorage.setItem('nisha:theme',value),theme);
        await student.page.goto(`${base}/delete-account.php`);
        const fits=await student.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1&&[...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox])')].every(node=>parseFloat(getComputedStyle(node).fontSize)>=16));
        ok(fits&&await student.page.locator('html').getAttribute('data-theme')===theme,`The deletion request remains readable in ${theme} at ${width}px.`);
    }
    await student.page.locator('#deletion-password').fill(password);await student.page.locator('#deletion-reason').fill('Browser privacy fixture');await student.page.locator('[name=confirmation]').check();
    await Promise.all([student.page.waitForNavigation({waitUntil:'domcontentloaded'}),student.page.locator('.auth-form button[type=submit]').click()]);
    ok((await student.page.locator('main').innerText()).includes('Your request was received.')&&sql('request',{id:fixture.student}).status==='pending','The real request form returns an honest receipt backed by a pending queue record.');
    ok((await student.page.locator('main').innerText()).includes('not completed deletion')&&!(await student.page.content()).includes(password),'A request receipt does not pretend cleanup already finished or disclose its password.');
    const signedOut=await student.context.request.get(`${base}/dashboard.php`,{maxRedirects:0});
    ok(signedOut.status()===303&&signedOut.headers().location==='login.php','Submitting the request signs the student out on the website.');
    await owner.page.goto(`${base}/admin-deletion.php`);
    ok((await owner.page.locator('main').innerText()).includes(emails.student),'The owner sees the real confirmed request.');
    reply=await post(owner.context,'admin-deletion.php',{csrf_token:'wrong',user_id:String(fixture.student),action:'complete',current_password:password,reason:'Browser verified removal.',confirmation:'1'});
    ok(reply.status===403&&sql('request',{id:fixture.student}).status==='pending','Owner fulfillment also rejects forged CSRF.');
    reply=await post(owner.context,'admin-deletion.php',{csrf_token:await token(owner.page),user_id:String(fixture.student),action:'complete',current_password:'wrong-password',reason:'Browser verified removal.',confirmation:'1'});
    ok(/password is incorrect/.test(reply.html)&&sql('request',{id:fixture.student}).status==='pending','Owner fulfillment requires the current administrator password.');
    for(const theme of['light','dark'])for(const width of[320,390,1440]){
        await owner.page.setViewportSize({width,height:900});await owner.page.evaluate(value=>localStorage.setItem('nisha:theme',value),theme);await owner.page.goto(`${base}/admin-deletion.php`);
        ok(await owner.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)&&await owner.page.locator('html').getAttribute('data-theme')===theme,`The owner request queue fits ${width}px in ${theme}.`);
    }
    const form=owner.page.locator('form.teacher-form').filter({has:owner.page.locator(`[name=user_id][value="${fixture.student}"]`)});
    await form.locator('[name=current_password]').fill(password);await form.locator('[name=reason]').fill('Browser verified removal.');
    await Promise.all([owner.page.waitForNavigation({waitUntil:'domcontentloaded'}),form.locator('button[value=begin]').click()]);
    ok(sql('request',{id:fixture.student}).status==='in_progress'&&sql('account',{id:fixture.student}).email===emails.student,'Marking in progress leaves the actual account intact.');
    const complete=owner.page.locator('form.teacher-form').filter({has:owner.page.locator(`[name=user_id][value="${fixture.student}"]`)});
    await complete.locator('[name=current_password]').fill(password);await complete.locator('[name=reason]').fill('Browser verified removal.');await complete.locator('[name=confirmation]').check();
    await Promise.all([owner.page.waitForNavigation({waitUntil:'domcontentloaded'}),complete.locator('button[value=complete]').click()]);
    const erased=sql('account',{id:fixture.student}),request=sql('request',{id:fixture.student});
    ok(request.status==='completed'&&request.resolved_at&&erased.name==='Deleted account'&&erased.email===`closed-${fixture.student}@example.invalid`,'Completing the real owner form performs account anonymisation and resolves the queue.');
    await owner.page.goto(`${base}/admin-deletion.php?status=completed`);
    ok(!(await owner.page.locator('main').innerText()).includes(emails.student)&&(await owner.page.locator('main').innerText()).includes('Deleted account'),'The completed queue no longer displays the deleted personal identity.');
    ok(errors.length===0&&external.length===0,'Deletion workflows run without browser errors or external requests.');
    console.log(`PASS: ${checks} mobile deletion browser/HTTP checks. Exact disposable fixtures cleaned; no production account removed.`);
})().catch(error=>{console.error(error.stack);process.exitCode=1;}).finally(async()=>{
    for(const context of contexts)await context.close();if(browser)await browser.close();
    if(fixture)try{sql('cleanup',{ids:Object.values(fixture)});}catch(_){console.error('Deletion browser fixture cleanup failed.');process.exitCode=1;}
});
