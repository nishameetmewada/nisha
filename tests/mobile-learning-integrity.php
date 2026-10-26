<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__); $suffix=bin2hex(random_bytes(6)); $scratch=sys_get_temp_dir().'/nisha-mobile-learning-'.$suffix;
$database='nisha_mobile_learning_'.$suffix; $dbUser='nisha_ml_'.$suffix; $dbPassword=bin2hex(random_bytes(24));
$admin=getenv('NISHA_TEST_MYSQL_ADMIN_BIN')?:'/workspace/.tools/bin/mariadb'; $created=false; $failed=false; $checks=0;
function ml_check(bool $condition,string $message):void {global $checks;if(!$condition)throw new LogicException('Mobile learning: '.$message);++$checks;}
function ml_fail(callable $call,int $status,string $message):void {try{$call();}catch(NishaMobileServiceException $exception){ml_check($exception->httpStatus===$status,$message);return;}throw new LogicException('Mobile learning: '.$message);}
function ml_sql(string $sql):void {global $admin;$p=proc_open([$admin],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Fixture client unavailable.');fwrite($pipes[0],$sql);fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Local fixture SQL failed.');}
function ml_keys(array $data):bool {foreach($data as $key=>$value){if(in_array($key,['answer','explanation','correct_text'],true))return true;if(is_array($value)&&ml_keys($value))return true;}return false;}
function ml_payload(array $exam,string $attempt):array{return ['exam_id'=>$exam['id'],'exam_version'=>$exam['version'],'attempt_id'=>$attempt,'answers'=>[],'elapsed_seconds'=>12,'completed_sections'=>array_column($exam['sections'],'id'),'assisted'=>true];}
try {
    mkdir($scratch,0700);mkdir($scratch.'/config',0700);mkdir($scratch.'/sessions',0700);
    file_put_contents($scratch.'/config/config.php','<?php return '.var_export(['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>$database,'db_user'=>$dbUser,'db_password'=>$dbPassword,'openai_api_key'=>'','teacher_video_hosts'=>['video.example.com']],true).';');chmod($scratch.'/config/config.php',0600);
    define('NISHA_ROOT',$scratch);ini_set('session.save_path',$scratch.'/sessions');
    require $root.'/includes/mobile-learning.php';
    foreach(nisha_exams() as $exam){$metadata=nisha_mobile_exam_metadata($exam);ml_check($metadata['requires_access']&&$metadata['resources']===[]&&array_filter($metadata['sections'],static fn($section)=>isset($section['items']))===[]&&!ml_keys($metadata),'Every full mock is metadata before access.');}
    $practice=nisha_resolve_exam('xp.ielts-academic.00001');$item=array_values(nisha_exam_items($practice))[0];
    $public=nisha_mobile_dispatch('content','GET',['id'=>$practice['id']],null);
    ml_check($public['exam']['content_source']==='generated'&&!ml_keys($public),'Generated guest questions remain answer-free and unreviewed.');
    $feedback=nisha_mobile_dispatch('feedback','POST',['exam_id'=>$practice['id'],'exam_version'=>$practice['version'],'item_id'=>$item['id'],'response'=>$item['answer']],null);
    ml_check($feedback['review']['score']===$feedback['review']['max_score'],'Guest feedback marks a canonical correct answer.');
    ml_fail(fn()=>nisha_mobile_dispatch('feedback','POST',['exam_id'=>$practice['id'],'exam_version'=>'stale','item_id'=>$item['id'],'response'=>$item['answer']],null),409,'Stale answers are not marked against new content.');
    ml_fail(fn()=>nisha_mobile_dispatch('content','GET',['id'=>'../config'],null),404,'Unknown session paths cannot resolve.');
    ml_fail(fn()=>nisha_mobile_dispatch('catalog','POST',[],null),405,'Method mismatches are rejected.');
    ml_fail(fn()=>nisha_mobile_dispatch('billing-settings','POST',[],null),404,'The student API has no owner pricing endpoint.');
    ml_fail(fn()=>nisha_mobile_dispatch('content','GET',['id'=>$practice['id'],'answers'=>['fake']],null),422,'Client question definitions are never accepted.');
    $page=nisha_mobile_dispatch('generated','GET',['course'=>'pte-core','kind'=>'questions','skill'=>'writing','page'=>'1'],null);
    ml_check($page['total']===1500&&count($page['items'])===24&&!ml_keys($page),'Generated filters expose bounded metadata, not a whole bank.');
    ml_fail(fn()=>nisha_mobile_dispatch('start_mock','POST',['exam_id'=>'ielts-academic-01','attempt_id'=>'mobile-fixture-attempt1'],null),401,'Full mock release requires a student identity.');
    ml_fail(fn()=>nisha_mobile_dispatch('feedback','POST',['exam_id'=>'ielts-academic-01','exam_version'=>'unused','item_id'=>'unused','response'=>'unused'],null),403,'Full papers cannot use the free feedback endpoint.');
    ml_fail(fn()=>nisha_mobile_dispatch('history','GET',[],null),401,'Guest callers cannot read any saved account history.');
    ml_fail(fn()=>nisha_mobile_dispatch('ai_status','GET',['request_id'=>'anything'],null),401,'Guest callers cannot inspect AI references.');
    if(!is_executable($admin))throw new RuntimeException('Local MariaDB wrapper required.');
    ml_sql("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '$dbUser'@'127.0.0.1' IDENTIFIED BY '$dbPassword'; GRANT SELECT,INSERT,UPDATE,DELETE ON `$database`.* TO '$dbUser'@'127.0.0.1'; USE `$database`; ".file_get_contents($root.'/database/schema.sql'));$created=true;
    $pdo=db();$users=[];
    foreach(['student','other','teacher'] as $role){$q=$pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$q->execute(['Mobile fixture '.$role,$role.'@example.test',password_hash('mobile-fixture-password-only',PASSWORD_DEFAULT)]);$users[$role]=['id'=>(int)$pdo->lastInsertId(),'name'=>'Fixture '.$role,'email'=>$role.'@example.test'];}
    $catalog=nisha_mobile_dispatch('catalog','GET',[],null);
    ml_check(count($catalog['courses'])===4&&count($catalog['full_mocks'])===4&&$catalog['generated']['stats']['question_count']===30000&&!ml_keys($catalog),'The mobile catalogue shares all four courses without answer leakage.');
    ml_check($catalog['purchase_in_app']===false&&$catalog['billing_mode']==='existing_access','Store builds do not route digital purchases through Stripe.');
    ml_fail(fn()=>nisha_mobile_dispatch('content','GET',['id'=>'gp.ielts-academic.00001'],null),404,'Private teacher drafts do not become generated publications.');
    $guest=nisha_mobile_dispatch('complete','POST',ml_payload($practice,'mobile-guest-practice1'),null);
    ml_check(!$guest['saved']&&$guest['result']['content_source']==='generated','Guest practice completion keeps honest source labels without account writes.');
    $saved=nisha_mobile_dispatch('complete','POST',ml_payload($practice,'mobile-saved-practice1'),$users['student']);
    ml_check($saved['saved']&&$saved['result_id']>0,'Signed-in generated practice saves to the normal account ledger.');
    $readingOther=nisha_resolve_exam('xp.ielts-academic.00002');
    ml_fail(fn()=>nisha_mobile_dispatch('complete','POST',ml_payload($readingOther,'mobile-saved-practice1'),$users['student']),409,'An attempt cannot be reassigned to a different session.');
    $first=nisha_mobile_dispatch('start_mock','POST',['exam_id'=>'xm.ielts-academic.0001','attempt_id'=>'mobile-first-full-mock1'],$users['student']);
    ml_check($first['grant']['kind']==='trial'&&!ml_keys($first['exam']),'The account reserves one sanitized generated full mock.');
    $resume=nisha_mobile_dispatch('start_mock','POST',['exam_id'=>'xm.ielts-academic.0001','attempt_id'=>'mobile-first-full-mock1'],$users['student']);
    ml_check($resume['grant']===$first['grant'],'Starting the same reference safely resumes the free mock.');
    ml_fail(fn()=>nisha_mobile_dispatch('start_mock','POST',['exam_id'=>'pte-core-01','attempt_id'=>'mobile-parallel-mock1'],$users['student']),402,'A second tab cannot reserve another free paper.');
    $done=nisha_mobile_dispatch('complete','POST',ml_payload($first['exam'],'mobile-first-full-mock1'),$users['student']);
    ml_check($done['saved']&&$done['mock_access']['trial_state']==='consumed'&&$done['result']['content_source']==='generated','Saving the full report atomically consumes the shared free allowance.');
    $changed=ml_payload($first['exam'],'mobile-first-full-mock1');$changed['elapsed_seconds']=99;
    $retry=nisha_mobile_dispatch('complete','POST',$changed,$users['student']);
    ml_check($retry['result_id']===$done['result_id']&&$retry['result']===$done['result'],'Changed retries return the original immutable report.');
    ml_fail(fn()=>nisha_mobile_dispatch('start_mock','POST',['exam_id'=>'pte-core-01','attempt_id'=>'mobile-second-full-mock'],$users['student']),402,'Further full mocks require the same paid access as the website.');
    ml_fail(fn()=>nisha_mobile_dispatch('complete','POST',ml_payload(nisha_resolve_exam('pte-core-01'),'mobile-forged-full-mock'),$users['student']),402,'A forged direct completion cannot expose a second marked paper.');
    $result=nisha_mobile_dispatch('result','GET',['id'=>(string)$done['result_id']],$users['student']);
    ml_check($result['saved']&&$result['result']===$done['result'],'Saved mobile reports preserve source and responses.');
    ml_fail(fn()=>nisha_mobile_dispatch('result','GET',['id'=>(string)$done['result_id']],$users['other']),404,'A different student cannot retrieve the report.');
    $history=nisha_mobile_dispatch('history','GET',['per_page'=>'1','page'=>'999'],$users['student']);
    ml_check($history['total']===2&&$history['page']===2&&count($history['items'])===1&&!array_key_exists('result_json',$history['items'][0]),'History bounds pages and omits full answer reports.');
    $second=nisha_mobile_dispatch('start_mock','POST',['exam_id'=>'ielts-general-01','attempt_id'=>'mobile-save-rollback1'],$users['other']);
    ml_sql("RENAME TABLE `$database`.exam_runs TO `$database`.exam_runs_saved;");
    try{$notSaved=nisha_mobile_dispatch('complete','POST',ml_payload($second['exam'],'mobile-save-rollback1'),$users['other']);}
    finally{ml_sql("RENAME TABLE `$database`.exam_runs_saved TO `$database`.exam_runs;");}
    $q=$pdo->prepare('SELECT status FROM commerce_trials WHERE user_id=?');$q->execute([$users['other']['id']]);
    ml_check(!$notSaved['saved']&&$q->fetchColumn()==='reserved','A failed result save does not consume the free trial.');
    $pdo->prepare("INSERT INTO teacher_profiles(user_id,display_name,bio,status) VALUES(?,?,?,'approved')")->execute([$users['teacher']['id'],'Fixture instructor','Artificial mobile test lesson.']);
    $pdo->prepare("INSERT INTO coaching_videos(teacher_id,title,description,course_id,skill,video_url,duration_seconds,status) VALUES(?,?,?,?,?,?,?,'published')")->execute([$users['teacher']['id'],'Clear writing','Original fixture teaching video.','ielts-academic','writing','https://video.example.com/lesson.mp4',300]);$video=(int)$pdo->lastInsertId();
    $lessons=nisha_mobile_dispatch('coaching','GET',['course'=>'ielts-academic','skill'=>'writing','q'=>'Clear'],null);
    ml_check($lessons['total']===1&&!isset($lessons['items'][0]['video_url'])&&!$lessons['items'][0]['can_play'],'Guest lesson search returns published metadata without media URLs.');
    ml_fail(fn()=>nisha_mobile_dispatch('lesson','GET',['id'=>(string)$video],$users['student']),402,'Unpaid students cannot fetch coaching media.');
    ml_fail(fn()=>nisha_mobile_dispatch('watch','POST',['action'=>'start','video_id'=>$video,'position'=>0],$users['student']),403,'Unpaid clients cannot start rewarded video progress.');
    $own=nisha_mobile_dispatch('lesson','GET',['id'=>(string)$video],$users['teacher']);
    ml_check($own['lesson']['video_url']==='https://video.example.com/lesson.mp4','A teacher may preview an allowed own published video.');
    $order=bin2hex(random_bytes(16));$pdo->prepare("INSERT INTO payment_orders(id,user_id,amount_minor,currency,access_days,status) VALUES(?,?,2900,'aud',30,'paid')")->execute([$order,$users['student']['id']]);
    $pdo->prepare('INSERT INTO student_entitlements(user_id,order_id,starts_at,expires_at) VALUES(?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 30 DAY)')->execute([$users['student']['id'],$order]);
    $paidLesson=nisha_mobile_dispatch('lesson','GET',['id'=>(string)$video],$users['student']);
    ml_check($paidLesson['lesson']['can_play']&&isset($paidLesson['lesson']['video_url']),'An existing paid pass unlocks the same lesson in the app.');
    $watch=nisha_mobile_dispatch('watch','POST',['action'=>'start','video_id'=>$video,'position'=>0],$users['student']);
    ml_check(is_string($watch['token'])&&strlen($watch['token'])===64,'Video progress retains the server-issued watch reference.');
    ml_fail(fn()=>nisha_mobile_dispatch('ai_review','POST',['request_id'=>'mobile-ai-reference-123','exam_id'=>$practice['id'],'exam_version'=>$practice['version'],'item_id'=>$item['id'],'attempt_id'=>'mobile-saved-practice1','response'=>'A response'],$users['student']),422,'An objective task cannot trigger a paid AI review.');
    $status=nisha_mobile_dispatch('ai_status','GET',['request_id'=>'mobile-ai-reference-123'],$users['student']);
    ml_check($status['state']==='not_found'&&$status['_status']===404,'Unknown AI references are read-only and never resubmitted.');
    ml_check((int)$pdo->query('SELECT COUNT(*) FROM ai_reviews')->fetchColumn()===0,'Service checks made no AI reservation or provider call.');
    echo "PASS: $checks mobile learning/service checks. Isolated local fixtures; no provider calls or production changes.\n";
}catch(Throwable $exception){fwrite(STDERR,($exception instanceof NishaMobileServiceException?'Mobile learning rejected ['.$exception->errorCode.']: '.$exception->publicMessage:($exception instanceof LogicException?$exception->getMessage():'Mobile learning fixture failed safely ('.get_class($exception).').'))."\n");$failed=true;}
finally{
    if($created){try{ml_sql("DROP DATABASE IF EXISTS `$database`; DROP USER IF EXISTS '$dbUser'@'127.0.0.1';");}catch(Throwable $exception){fwrite(STDERR,"Mobile learning fixture cleanup failed.\n");$failed=true;}}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    foreach(glob($scratch.'/sessions/*')?:[]as$file)unlink($file);if(is_file($scratch.'/config/config.php'))unlink($scratch.'/config/config.php');foreach(['sessions','config','']as$dir)if(is_dir($scratch.'/'.$dir))rmdir($scratch.'/'.$dir);
}
exit($failed?1:0);
