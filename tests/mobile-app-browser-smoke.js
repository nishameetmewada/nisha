/* Local mobile UI checks. API replies are explicit browser fixtures; no paid service calls. */
'use strict';
const {chromium}=require('playwright');
const {spawn,execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const crypto=require('node:crypto');
const fs=require('node:fs');
const path=require('node:path');
const net=require('node:net');
const root=path.resolve(__dirname,'..');
const php=process.env.NISHA_PHP_BIN||'/workspace/.tools/bin/php';
const pack=JSON.parse(fs.readFileSync(path.join(root,'mobile/src/data/offline-sessions.json'),'utf8'));
const token='mobile_browser_fixture_'+crypto.randomBytes(24).toString('hex');
const password=crypto.randomBytes(20).toString('hex');
const user={id:91,name:'Mobile Local Student',email:'mobile-ui@example.test',created_at:'2026-10-01 00:00:00'};
const access={paid_active:false,paid_until:null,trial_state:'available'};
const fixtures=JSON.parse(execFileSync(php,['-r',`
require 'includes/mobile-learning.php';
$catalog=nisha_mobile_learning_catalog();$samples=[];
foreach(nisha_learning_library()['sessions'] as $e){if($e['session_type']!=='practice')continue;foreach($e['sections'] as$s)foreach($s['items']as$q){if(!isset($samples[$q['type']])){$one=$e;$one['sections'][0]['items']=[$q];$samples[$q['type']]=['canonical'=>$one,'public'=>nisha_public_exam($one)];}}}
$full=nisha_exams()['ielts-academic-01'];
echo json_encode(['catalog'=>$catalog,'samples'=>$samples,'full'=>nisha_public_exam($full),'metadata'=>nisha_mobile_exam_metadata($full)],JSON_THROW_ON_ERROR);
`],{cwd:root,encoding:'utf8'}));
let server,browser,base,checks=0,apiUnavailable=true,aiInterrupted=true,expireSession=false;
const errors=[],requests=[];
const ok=(condition,label)=>{assert(condition,label);checks++;console.log('PASS',label)};
function actualFeedback(exam,item,response){return JSON.parse(execFileSync(php,['-r',`require 'includes/mobile-learning.php';$v=json_decode(stream_get_contents(STDIN),true);echo json_encode(nisha_mobile_learning_feedback($v),JSON_THROW_ON_ERROR);`],{cwd:root,input:JSON.stringify({exam_id:exam.id,exam_version:exam.version,item_id:item.id,response}),encoding:'utf8'}));}
const review={score:{value:6,scale:'IELTS writing practice'},summary:'Your position is clear; develop the supporting examples.',strengths:['Clear main position.'],improvements:[{issue:'Limited development',evidence:'useful',suggestion:'Add an example.',example:'For example, a small pilot can reveal barriers.'}],next_steps:['Develop an example.'],limitations:['This is a browser fixture, not a real AI evaluation.']};
async function port(){const probe=net.createServer();await new Promise(resolve=>probe.listen(0,'127.0.0.1',resolve));const value=probe.address().port;await new Promise(resolve=>probe.close(resolve));return value}
async function clickTab(page,tab){await page.locator(`[data-tab="${tab}"]`).click();}
async function chooseOffline(page,session){await clickTab(page,'practice');await page.getByRole('button',{name:'On this phone',exact:true}).click();await page.locator('#practice-course').selectOption(session.course_id);await page.locator('#practice-skill').selectOption('all');await page.locator(`[data-session-id="${session.id}"]`).click();await page.locator('#runner').waitFor();}
function first(type,course){return pack.sessions.find(e=>(!course||e.course_id===course)&&e.sections[0].items[0].type===type)}
async function finish(page){await page.locator('#finish-practice').click();await page.locator('dialog[open] button').filter({hasText:'Finish & review'}).click();await page.locator('.result-score').waitFor();}
(async()=>{
 const listen=await port();base=`http://127.0.0.1:${listen}`;
 server=spawn('npm',['run','dev','--','--port',String(listen),'--strictPort'],{cwd:path.join(root,'mobile'),env:{...process.env,npm_config_cache:'/workspace/.npm-cache'},stdio:['ignore','ignore','pipe'],detached:process.platform!=='win32'});
 let serverErrors='';server.stderr.on('data',chunk=>serverErrors+=chunk);
 for(let n=0;n<60;n++){try{if((await fetch(base)).ok)break}catch{}await new Promise(resolve=>setTimeout(resolve,100));}
 browser=await chromium.launch({executablePath:process.env.NISHA_CHROMIUM_BIN||'/usr/bin/chromium',headless:true,args:['--no-sandbox','--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream','--autoplay-policy=no-user-gesture-required']});
 const context=await browser.newContext({viewport:{width:390,height:844},permissions:['microphone']});
 await context.addInitScript(()=>{window.structuredClone=undefined;Object.defineProperty(crypto,'randomUUID',{value:undefined,configurable:true});if(window.HTMLDialogElement){HTMLDialogElement.prototype.showModal=undefined;HTMLDialogElement.prototype.close=undefined}const original=Date.now.bind(Date);window.__mobileClockOffset=0;Date.now=()=>original()+window.__mobileClockOffset;});
 await context.route('https://nishaglobal.com/api/mobile.php**',async route=>{
  const request=route.request(),url=new URL(request.url()),action=url.searchParams.get('action');
  const headers={'Access-Control-Allow-Origin':base,'Access-Control-Allow-Headers':'Content-Type, Authorization','Access-Control-Allow-Methods':'GET, POST, OPTIONS','Content-Type':'application/json'};
  if(request.method()==='OPTIONS')return route.fulfill({status:204,headers,body:''});
  let data={};if((request.headers()['content-type']||'').includes('application/json'))data=request.postDataJSON()||{};
  requests.push({action,method:request.method(),data,url:url.href,authorization:request.headers().authorization||'',contentType:request.headers()['content-type']||'',body:request.postDataBuffer()});
  if(apiUnavailable)return route.abort('internetdisconnected');
  const send=(body,status=200)=>route.fulfill({status,headers,body:JSON.stringify({protocol_version:1,...body})});
  if(action==='catalog')return send(fixtures.catalog);
  if(action==='login'||action==='register'){assert.equal(data.password,password);return send({success:true,user,access_token:token});}
  if(action==='me')return expireSession?send({error:'Your session ended.',code:'mobile_token_invalid'},401):send({success:true,user,access});
  if(action==='logout')return send({success:true});
  if(action==='generated'){const sample=url.searchParams.get('skill')==='speaking'?fixtures.samples.spoken.public:fixtures.samples.choice.public;return send({success:true,items:[{id:sample.id,name:'Canonical generated card fixture',skill:url.searchParams.get('skill')||'reading',session_type:'practice',item_count:1}],page:1,pages:2,total:48});}
  if(action==='content'){
   const id=url.searchParams.get('id');if(id==='ielts-academic-01')return send({success:true,exam:fixtures.metadata});
   const sample=Object.values(fixtures.samples).find(value=>value.public.id===id);return send({success:true,exam:sample?.public||fixtures.samples.choice.public});
  }
  if(action==='start_mock')return send({success:true,exam:fixtures.full,grant:{kind:'trial'},access:{...access,trial_state:'reserved'}});
  if(action==='feedback')return send(actualFeedback({id:data.exam_id,version:data.exam_version},{id:data.item_id},data.response));
  if(action==='complete')return send({success:true,saved:true,result_id:44,mock_access:{...access,trial_state:'consumed'},result:{exam_id:data.exam_id,name:'Saved mobile fixture',exam:'IELTS',exam_version:data.exam_version,attempt_id:data.attempt_id,session_type:'full',objective_score:0,objective_total:80,review:[],responses:data.answers}});
  if(action==='history')return send({success:true,items:[{id:44,exam_name:'Saved mobile fixture',objective_score:0,objective_total:80,created_at:'2026-10-26 10:00:00'}]});
  if(action==='result'){const q=fixtures.samples.essay.canonical.sections[0].items[0];return send({success:true,saved:true,result_id:44,result:{exam_id:fixtures.samples.essay.public.id,name:'Saved writing fixture',exam:'IELTS',exam_version:fixtures.samples.essay.public.version,attempt_id:'mobile-saved-fixture-attempt',session_type:'practice',objective_score:0,objective_total:0,review:[{id:q.id,type:'essay',response:'A clear position with useful examples.',prompt:q.prompt,item_snapshot:q,score:null,correct_answer:null}],responses:{[q.id]:'A clear position with useful examples.'},resources:fixtures.samples.essay.public.resources}});}
  if(action==='ai_review'){if(aiInterrupted){aiInterrupted=false;return route.abort('failed')}return send({success:true,review});}
  if(action==='ai_status')return send({success:true,state:'completed',review});
  if(action==='coaching')return send({success:true,items:[{id:7,title:'Developing a clear position',teacher_name:'Local Teacher',course_id:'ielts-academic',skill:'writing',description:'A local browser fixture.',can_play:true}],total:1,available_total:1});
  if(action==='lesson')return send({success:true,lesson:{id:7,title:'Developing a clear position',video_url:'https://media.example.test/lesson.mp4'}});
  if(action==='watch')return send({success:true,token:'a'.repeat(64),message:'Local progress fixture.'});
  if(action==='deletion_request')return send({success:true,status:'pending'},202);
  return send({error:`Unexpected local fixture action: ${action}`},500);
 });
 await context.route('https://media.example.test/**',route=>route.abort());
 const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
 await page.goto(base);await page.getByRole('heading',{name:/Big dreams/}).waitFor();
 ok((await page.locator('.quick-stats').innerText()).includes('80'),'The app opens its complete 80-task offline pack when the website is unavailable');
 for(const mode of ['light','dark']){
  await page.evaluate(value=>localStorage.setItem('nisha-mobile:theme',value),mode);await page.reload();
  for(const width of [320,390,768,1440]){await page.setViewportSize({width,height:950});for(const tab of ['home','practice','mocks','coaching','account']){await clickTab(page,tab);ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)&&await page.locator('html').getAttribute('data-theme')===mode,`${tab} fits ${width}px in ${mode}`);}}
 }
 await page.setViewportSize({width:390,height:844});await clickTab(page,'practice');
 for(const course of ['ielts-academic','ielts-general','pte-academic','pte-core']){await page.locator('#practice-course').selectOption(course);ok(await page.locator('.practice-card').count()===20,`${course} has its actual 20 offline tasks`);}
 for(const type of ['choice','multi','short','gaps','reorder','highlight','essay','spoken']){
  const session=first(type),q=session.sections[0].items[0];await chooseOffline(page,session);
  const controls=page.locator('#answer-controls');
  if(type==='choice')await controls.locator(`input[value="${q.answer}"]`).check();
  if(type==='multi')for(const id of q.answer)await controls.locator(`input[value="${id}"]`).check();
  if(type==='short')await controls.locator('input').fill(q.answer[0]);
  if(type==='gaps')for(const blank of q.blanks)await controls.locator(`[aria-label="Blank ${blank.id}"]`).fill(blank.answer[0]);
  if(type==='reorder'){await controls.getByRole('button',{name:'Move up'}).nth(1).click();await controls.getByRole('button',{name:'Confirm this order'}).click();}
  if(type==='highlight')for(const id of q.answer)await controls.locator('.word-button').nth(Number(id)).click();
  if(type==='essay'||type==='spoken')await controls.locator('textarea').fill('This is a useful local practice response.');
  await page.locator('#check-answer').click();await page.locator('.feedback-panel').waitFor();
  ok(await page.locator('.feedback-panel').isVisible(),`Offline ${type} controls accept a response and show actual local feedback`);
  if(type==='choice'){await finish(page);ok((await page.locator('.result-score').innerText()).includes('1 / 1'),'An actual correct offline choice finishes with a locally marked point');}
 }
 ok(!requests.some(request=>['feedback','complete','ai_review'].includes(request.action)),'Offline practice never uploads responses or requests AI feedback');
 const spoken=first('spoken');await chooseOffline(page,spoken);await page.locator('[data-record]').click();await page.waitForTimeout(200);await page.locator('[data-record]').click();await page.locator('#answer-controls audio').waitFor();
 const audio=await page.locator('#answer-controls audio').getAttribute('src');ok(audio.startsWith('data:audio/wav;base64,UklGR'),'Browser microphone capture produces a real PCM-WAV recording');
 await clickTab(page,'home');await chooseOffline(page,spoken);ok(await page.locator('#answer-controls audio').count()===0,'Leaving the practice flow discards its private recording');
 const short=first('short');await chooseOffline(page,short);await page.locator('#answer-controls input').fill('Retained local answer');await page.getByRole('button',{name:'Save & leave'}).click();await clickTab(page,'home');await page.getByRole('button',{name:/Resume your practice/}).click();
 ok(await page.locator('#answer-controls input').inputValue()==='Retained local answer','Save and resume retains the account-scoped text draft');
 apiUnavailable=false;await clickTab(page,'practice');await page.getByRole('button',{name:'Question types',exact:true}).click();await page.waitForFunction(()=>document.querySelectorAll('.practice-card').length>0);
 ok((await page.locator('.results-count').innerText()).includes('guided question types'),'The online starter library exposes real canonical question-type modules');
 await page.locator('#practice-course').selectOption('ielts-academic');await page.locator('#practice-skill').selectOption('all');
 const choice=fixtures.samples.choice.public;const canonicalChoice=fixtures.samples.choice.canonical.sections[0].items[0];
 // Use the actual canonical card if available; public modules may group multiple items.
 await page.getByRole('button',{name:'Generated library',exact:true}).click();await page.locator(`[data-session-id="${choice.id}"]`).click();await page.locator('#runner').waitFor();
 await page.locator(`#answer-controls input[value="${canonicalChoice.answer}"]`).check();await page.locator('#check-answer').click();await page.locator('.feedback-panel').waitFor();
 ok((await page.locator('.feedback-panel').innerText()).includes('Accepted answer:'),'Online canonical feedback renders the server correct_answer field');
 await clickTab(page,'account');await page.locator('[name=email]').fill(user.email);await page.locator('[name=password]').fill(password);await page.locator('form button[type=submit]').click();await page.getByRole('heading',{name:user.name,exact:true}).waitFor();
 ok(!JSON.stringify(await page.evaluate(()=>({...localStorage}))).includes(token),'Browser account tokens stay in memory, outside device draft storage');
 ok(requests.some(request=>request.action==='login'&&request.method==='POST'&&request.contentType.includes('application/json')&&request.data.password===password),'Sign-in sends the explicit JSON request body through the mobile API');
 await page.getByRole('button',{name:'Load saved account history'}).click();await page.locator('.online-history button').click();await page.locator('.review-card summary').click();
 ok(await page.locator('.ai-panel').isVisible(),'A reopened saved writing result can request its original answer’s AI review');
 await page.getByRole('button',{name:'Review my answer',exact:true}).click();await page.getByRole('button',{name:'Check my pending review'}).waitFor();await page.getByRole('button',{name:'Check my pending review'}).click();await page.locator('.ai-feedback').getByText(review.summary).waitFor();
 ok(requests.filter(request=>request.action==='ai_review').length===1&&requests.some(request=>request.action==='ai_status'),'A dropped AI connection recovers by reference without silently sending another review');
 await clickTab(page,'practice');await page.locator('#practice-skill').selectOption('speaking');await page.getByRole('button',{name:'Generated library',exact:true}).click();await page.locator(`[data-session-id="${fixtures.samples.spoken.public.id}"]`).click();await page.locator('[data-record]').click();await page.waitForTimeout(200);await page.locator('[data-record]').click();await page.locator('#answer-controls audio').waitFor();
 ok(requests.filter(request=>request.action==='ai_review').length===1,'Recording a spoken answer does not automatically upload it for AI review');
 await page.getByRole('button',{name:'Review my answer',exact:true}).click();await page.getByText('Your AI practice feedback is ready.',{exact:true}).waitFor();
 const uploaded=requests.filter(request=>request.action==='ai_review').at(-1);
 ok(uploaded.contentType.startsWith('multipart/form-data;')&&uploaded.body.includes(Buffer.from('RIFF'))&&!uploaded.body.includes(Buffer.from('name="transcript"')),'An explicit recording review uploads WAV bytes without silently adding a transcript');
 await clickTab(page,'coaching');await page.getByRole('button',{name:'Open lesson'}).click();await page.locator('video').waitFor();
 await page.evaluate(()=>{const video=document.querySelector('video');Object.defineProperty(video,'paused',{get:()=>false,configurable:true});Object.defineProperty(video,'ended',{get:()=>false,configurable:true});video.currentTime=12;video.dispatchEvent(new Event('play'));});
 await page.waitForFunction(()=>Boolean(document.querySelector('video')));await page.waitForTimeout(100);await page.evaluate(()=>document.querySelector('video').dispatchEvent(new Event('timeupdate')));await page.waitForTimeout(100);
 ok(requests.some(request=>request.action==='watch'&&request.data.action==='heartbeat'&&request.data.playing===true&&request.data.visible===true),'Coaching sends the canonical heartbeat, playing and visibility fields');
 await clickTab(page,'mocks');await page.locator('[data-session-id="ielts-academic-01"]').click();await page.getByRole('button',{name:'Begin mock',exact:true}).click();await page.locator('#runner').waitFor();
 await page.evaluate(()=>window.__mobileClockOffset=3*3600*1000);await page.locator('.result-score').waitFor({timeout:10000});ok(requests.some(request=>request.action==='complete'&&request.data.completed_sections.length===fixtures.full.sections.length&&request.data.elapsed_seconds>=10800),'A long background interval exhausts the original section budgets instead of restarting timers');await clickTab(page,'mocks');
 ok((await page.locator('main').innerText()).includes('Your free full mock is complete'),'Submitting a full mock updates the displayed free-access state from the saved response');
 await clickTab(page,'account');expireSession=true;await page.getByRole('button',{name:'Refresh account',exact:true}).click();await page.locator('form.auth-form').waitFor();
 ok(!JSON.stringify(await page.evaluate(()=>({...localStorage}))).includes(token),'A revoked mobile session returns to sign-in and retains no token in localStorage');
 ok(errors.length===0,`Mobile UI completes without JavaScript exceptions (${errors.join('; ')})`);
 console.log(`PASS: ${checks} mobile app browser checks. Offline grading and explicit API fixtures; no paid calls.`);
})().catch(error=>{console.error(error.stack);if(errors.length)console.error(errors);process.exitCode=1}).finally(async()=>{if(browser)await browser.close();if(server){try{if(process.platform==='win32')server.kill('SIGTERM');else process.kill(-server.pid,'SIGTERM')}catch{}}});
