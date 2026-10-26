<?php
declare(strict_types=1);
// A disposable local database. No production identities or remote services.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__); $suffix=bin2hex(random_bytes(6)); $database='nisha_delete_'.$suffix; $dbUser='delete_'.$suffix;
$dbSecret=bin2hex(random_bytes(24)); $scratch=sys_get_temp_dir().'/nisha-delete-'.$suffix;
$admin=getenv('NISHA_TEST_MYSQL_ADMIN_BIN')?:'/workspace/.tools/bin/mariadb'; $checks=0; $failed=false; $created=false;
function md_sql(string $sql): void { global $admin; $p=proc_open([$admin],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes); if(!is_resource($p))throw new RuntimeException('Local client unavailable.'); fwrite($pipes[0],$sql);fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Isolated SQL failed.'); }
function md_ok(bool $ok,string $label):void{global$checks;if(!$ok)throw new LogicException('TEST: '.$label);++$checks;}
function md_reject(callable $callback,string $label):void{try{$callback();}catch(InvalidArgumentException|NishaCommerceException|NishaAiException|NishaMobileException $e){md_ok(true,$label);return;}throw new LogicException('TEST: '.$label);}
function md_count(string $table,int $userId,string $field='user_id'):int{$q=db()->prepare('SELECT COUNT(*) FROM '.$table.' WHERE '.$field.'=?');$q->execute([$userId]);return(int)$q->fetchColumn();}
try {
    mkdir($scratch,0700);mkdir($scratch.'/config',0700);mkdir($scratch.'/sessions',0700);
    md_sql("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '$dbUser'@'127.0.0.1' IDENTIFIED BY '$dbSecret'; GRANT SELECT,INSERT,UPDATE,DELETE ON `$database`.* TO '$dbUser'@'127.0.0.1';");$created=true;
    md_sql("USE `$database`;\n".file_get_contents($root.'/database/schema.sql'));
    file_put_contents($scratch.'/config/config.php','<?php return '.var_export(['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>$database,'db_user'=>$dbUser,'db_password'=>$dbSecret,'admin_user_ids'=>[3],'teacher_video_hosts'=>['videos.example.test'],'site_url'=>'https://nisha.example','stripe_secret_key'=>'sk_test_deletion_fixture','stripe_webhook_secret'=>'whsec_deletion_fixture','payments_enabled'=>false],true).';');chmod($scratch.'/config/config.php',0600);
    define('NISHA_ROOT',$scratch);ini_set('session.save_path',$scratch.'/sessions');require$root.'/includes/mobile-deletion.php';require$root.'/includes/mobile-learning.php';
    $password='Deletion-fixture-password-123';$users=[];
    foreach(['owner','target','configured','other']as$role){$q=db()->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$q->execute(['Fixture '.$role,$role.'@example.test',password_hash($password,PASSWORD_DEFAULT)]);$users[$role]=['id'=>(int)db()->lastInsertId(),'name'=>'Fixture '.$role,'email'=>$role.'@example.test','created_at'=>'2026-01-01 00:00:00'];}
    $owner=$users['owner']['id'];$target=$users['target']['id'];$other=$users['other']['id'];$configured=$users['configured']['id'];
    db()->prepare("INSERT INTO user_roles(user_id,role) VALUES(?,'admin')")->execute([$owner]);
    $confirmation=['current_password'=>$password,'reason'=>'Verified confirmed privacy removal.','confirmation'=>true];
    md_reject(fn()=>nisha_mobile_deletion_queue(db(),$target),'A student cannot inspect the owner deletion queue.');
    md_reject(fn()=>nisha_mobile_deletion_action(db(),$target,$other,'complete',$confirmation),'A student cannot fulfill another account request.');
    md_reject(fn()=>nisha_mobile_deletion_action(db(),$owner,$other,'complete',$confirmation),'A user without an actual request cannot be erased.');
    nisha_mobile_deletion_request(db(),$users['owner'],['password'=>$password,'confirmation'=>true],'fixture-owner');
    md_reject(fn()=>nisha_mobile_deletion_action(db(),$owner,$owner,'complete',$confirmation),'The only active owner cannot erase their own account.');
    md_ok(nisha_admin_accounts_is_admin(db(),$owner)&&nisha_account_login_version(db(),$owner)!==null,'Self-lockout rejection preserves the active owner.');
    nisha_mobile_deletion_request(db(),$users['configured'],['password'=>$password,'confirmation'=>true],'fixture-configured');
    md_reject(fn()=>nisha_mobile_deletion_action(db(),$owner,$configured,'complete',$confirmation),'Configured recovery administrator cannot be erased by a browser action.');
    md_ok(md_count('users',$configured,'id')===1,'Configured recovery account survives rejected removal.');
    db()->beginTransaction();$token=nisha_mobile_issue_token(db(),$users['target'],'Private target device');db()->commit();
    nisha_mobile_deletion_request(db(),$users['target'],['password'=>$password,'confirmation'=>true,'reason'=>'Please remove target@example.test and my answers.'],'fixture-target');
    $queue=nisha_mobile_deletion_queue(db(),$owner);md_ok($queue['total']===3&&count($queue['items'])===3,'Owner sees the bounded real request queue.');
    md_ok(!str_contains(json_encode($queue),$password)&&!str_contains(json_encode($queue),'password_hash')&&!str_contains(json_encode($queue),$token['access_token']),'Queue contains no credentials or bearer material.');
    md_reject(fn()=>nisha_mobile_deletion_action(db(),$owner,$target,'complete',array_replace($confirmation,['current_password'=>'wrong-password'])),'Fulfillment requires current owner reauthentication.');
    md_reject(fn()=>nisha_mobile_deletion_action(db(),$owner,$target,'complete',array_replace($confirmation,['confirmation'=>false])),'Completion requires an explicit removal confirmation.');
    md_ok(nisha_mobile_deletion_action(db(),$owner,$target,'begin',$confirmation)['status']==='in_progress','In-progress action does not claim completion.');
    md_ok(db()->query("SELECT email FROM users WHERE id=$target")->fetchColumn()==='target@example.test','Starting review does not prematurely anonymise the account.');
    db()->prepare("INSERT INTO attempts(user_id,exam,skill,score,total) VALUES(?,'IELTS','Reading',1,1)")->execute([$target]);
    db()->prepare("INSERT INTO exam_runs(user_id,exam_id,attempt_id,exam_name,exam,responses_json,result_json,objective_score,objective_total,elapsed_seconds,completed_sections_json) VALUES(?,'practice.fixture','private-fixture-attempt','Private report','IELTS','{\"answer\":\"private answer\"}','{}',1,1,1,'[]')")->execute([$target]);
    db()->prepare("INSERT INTO ai_reviews(user_id,request_id,exam_id,exam_version,item_id,attempt_id,exam_name,task_type,mode,response_hash,cache_key,claim_token,status,response_text) VALUES(?,'private-review','practice.fixture','v1','item','attempt','Private report','Write Essay','writing',?,?,?,'completed','private AI answer')")->execute([$target,str_repeat('a',64),str_repeat('b',64),str_repeat('c',32)]);
    db()->prepare("INSERT INTO ai_review_usage(usage_day,scope_key,used_count) VALUES(UTC_DATE(),?,1)")->execute(['user-'.$target]);
    foreach([$target,$other]as$id)db()->prepare("INSERT INTO teacher_profiles(user_id,display_name,bio,status) VALUES(?,?,'Personal teaching biography','approved')")->execute([$id,'Teacher '.$id]);
    db()->prepare("INSERT INTO coaching_videos(teacher_id,title,description,video_url,duration_seconds,course_id,skill,status) VALUES(?,'Personal lesson','Personal lesson details','https://videos.example.test/lesson.mp4',60,'ielts-academic','reading','published')")->execute([$target]);$video=(int)db()->lastInsertId();
    db()->prepare("INSERT INTO coaching_videos(teacher_id,title,description,video_url,duration_seconds,course_id,skill,status) VALUES(?,'Other lesson','Other teacher details','https://videos.example.test/other.mp4',60,'ielts-academic','reading','published')")->execute([$other]);$otherVideo=(int)db()->lastInsertId();
    db()->prepare("INSERT INTO coaching_watch_sessions(user_id,video_id,period,video_revision,token_hash,last_seen) VALUES(?,?,'2026-01',1,?,UTC_TIMESTAMP())")->execute([$target,$video,str_repeat('d',64)]);
    db()->prepare("INSERT INTO coaching_watch_presence(user_id,video_id,video_revision,token_hash) VALUES(?,?,1,?)")->execute([$target,$video,str_repeat('d',64)]);
    db()->prepare("INSERT INTO coaching_qualified_views(user_id,video_id,period,teacher_id,video_revision) VALUES(?,?,'2026-01',?,1)")->execute([$other,$video,$target]);
    $order=str_repeat('e',32);db()->prepare("INSERT INTO payment_orders(id,user_id,amount_minor,currency,access_days,status) VALUES(?,?,2900,'aud',30,'paid')")->execute([$order,$target]);
    // This genuine pre-deletion order has not settled yet. A trusted, bound
    // payment notification must still update its financial ledger afterwards.
    $lateOrder=str_repeat('f',32);$lateCheckout='cs_test_deletion_late_fixture';
    db()->prepare("INSERT INTO payment_orders(id,user_id,amount_minor,currency,access_days,checkout_id,status) VALUES(?,?,1700,'aud',30,?,'pending')")->execute([$lateOrder,$target,$lateCheckout]);
    db()->prepare("INSERT INTO student_entitlements(user_id,order_id,starts_at,expires_at) VALUES(?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY))")->execute([$target,$order]);
    db()->prepare("INSERT INTO teacher_profit_periods(period,currency,revenue_cents,refunds_cents,expenses_cents,net_profit_cents,pool_cents,share_basis_points,verification_note,approved_by) VALUES('2026-01','AUD',10000,0,0,10000,9000,9000,'Verified local fixture accounting.',?)")->execute([$owner]);$period=(int)db()->lastInsertId();
    db()->prepare("INSERT INTO teacher_reward_allocations(period_id,teacher_id,qualified_views,currency,amount_cents,status,payout_reference) VALUES(?,?,1,'AUD',9000,'paid','paid-transfer-reference')")->execute([$period,$target]);
    nisha_admin_accounts_audit(db(),$owner,$target,'edit_profile','Edited Fixture target using target@example.test',['id'=>$target,'name'=>'Fixture target','email'=>'target@example.test'],['id'=>$target,'name'=>'Fixture target','email'=>'target@example.test']);
    nisha_admin_accounts_audit(db(),$target,$other,'edit_profile','Edited another account by Fixture target',['id'=>$other,'name'=>'Other student','email'=>'other@example.test'],['id'=>$other,'name'=>'Other student','email'=>'other@example.test']);
    md_sql("RENAME TABLE `$database`.coaching_videos TO `$database`.coaching_videos_fixture;");
    try{nisha_mobile_deletion_action(db(),$owner,$target,'complete',$confirmation);throw new LogicException('TEST: Missing cleanup table was accepted.');}catch(PDOException $exception){md_ok(true,'A mid-cleanup storage failure aborts completion.');}
    md_sql("RENAME TABLE `$database`.coaching_videos_fixture TO `$database`.coaching_videos;");
    md_ok(md_count('attempts',$target)===1&&md_count('exam_runs',$target)===1&&md_count('ai_reviews',$target)===1,'Failure rolls all private-history cleanup back.');
    md_ok(db()->query("SELECT status FROM mobile_deletion_requests WHERE user_id=$target")->fetchColumn()==='in_progress'&&db()->query("SELECT email FROM users WHERE id=$target")->fetchColumn()==='target@example.test','Failure does not mark completed or lose the original identity.');
    $done=nisha_mobile_deletion_action(db(),$owner,$target,'complete',$confirmation);md_ok($done['status']==='completed'&&$done['financial_references_retained'],'Successful completion reports actual removal and retained references.');
    $account=db()->query("SELECT name,email,password_hash FROM users WHERE id=$target")->fetch();
    md_ok($account['name']==='Deleted account'&&$account['email']==='closed-'.$target.'@example.invalid'&&!password_verify($password,$account['password_hash']),'Completion anonymises identity and removes the usable password.');
    md_ok(nisha_account_login_version(db(),$target)===null,'The retained pseudonymous account stays suspended.');
    foreach(['attempts','exam_runs','ai_reviews','coaching_watch_presence','coaching_watch_sessions','mobile_access_tokens']as$table)md_ok(md_count($table,$target)===0,'Personal data cleared from '.$table);
    md_ok(db()->query("SELECT bio FROM teacher_profiles WHERE user_id=$target")->fetchColumn()===''&&db()->query("SELECT status FROM teacher_profiles WHERE user_id=$target")->fetchColumn()==='rejected','Teacher biography is cleared and publication eligibility revoked.');
    $lesson=db()->query("SELECT title,description,video_url,status FROM coaching_videos WHERE id=$video")->fetch();md_ok($lesson['title']==='Removed lesson'&&$lesson['description']===''&&$lesson['video_url']===''&&$lesson['status']==='rejected','Personal lesson sources are removed while preserving referenced IDs.');
    md_ok(md_count('coaching_qualified_views',$target,'teacher_id')===1&&md_count('teacher_reward_allocations',$target,'teacher_id')===1,'Qualified financial evidence and allocated teacher rewards survive.');
    md_ok(db()->query("SELECT amount_minor FROM payment_orders WHERE id='$order'")->fetchColumn()==2900&&db()->query("SELECT amount_cents FROM teacher_reward_allocations WHERE teacher_id=$target")->fetchColumn()==9000,'Paid order and reward monetary amounts remain unchanged.');
    md_ok(!nisha_commerce_has_paid(db(),$target),'Account completion revokes active access without deleting its financial source.');
    $request=db()->query("SELECT * FROM mobile_deletion_requests WHERE user_id=$target")->fetch();md_ok($request['status']==='completed'&&$request['resolved_at']!==null&&$request['reason']==='','The queue records resolution and clears the original personal request text.');
    $audit=db()->query("SELECT before_json,after_json,reason FROM admin_account_audit WHERE target_id=$target OR actor_id=$target")->fetchAll();
    md_ok(!str_contains(json_encode($audit),'target@example.test')&&!str_contains(json_encode($audit),'Fixture target'),'Retained account-change audit removes the deleted personal identity.');
    md_ok(str_contains(json_encode($audit),'Other student')&&str_contains(json_encode($audit),'other@example.test'),'Redaction preserves other students’ audit identities.');
    $auditCount=(int)db()->query('SELECT COUNT(*) FROM admin_account_audit')->fetchColumn();$again=nisha_mobile_deletion_action(db(),$owner,$target,'complete',$confirmation);
    md_ok(!empty($again['already_completed'])&&(int)db()->query('SELECT COUNT(*) FROM admin_account_audit')->fetchColumn()===$auditCount,'Retrying completed cleanup is idempotent.');
    md_reject(fn()=>nisha_mobile_authenticate(db(),$token['access_token']),'Earlier app tokens cannot authenticate after removal.');
    $practice=nisha_resolve_exam('xp.ielts-academic.00001');$payload=['exam_id'=>$practice['id'],'exam_version'=>$practice['version'],'attempt_id'=>'stale-after-delete-0001','answers'=>[],'elapsed_seconds'=>1,'completed_sections'=>[],'assisted'=>true];
    md_reject(fn()=>nisha_complete_exam($practice,$payload,$users['target']),'A stale pre-deletion user DTO cannot recreate saved private exam data.');
    md_reject(fn()=>nisha_ai_claim(db(),$target,[]),'A stale pre-deletion request cannot claim new AI storage or provider quota.');
    md_reject(fn()=>nisha_teacher_watch($users['target'],['action'=>'start','video_id'=>$otherVideo,'position'=>0]),'A stale pre-deletion request cannot recreate video watch storage.');
    md_ok(md_count('exam_runs',$target)===0&&md_count('ai_reviews',$target)===0&&md_count('coaching_watch_sessions',$target)===0,'Rejected stale writes leave private data erased.');
    // Direct trusted-helper fixture only: signature verification is covered by
    // the HTTP payment suite, and no Stripe network request is made here.
    $lateEvent=['id'=>'evt_deletion_late_fixture','type'=>'checkout.session.completed','livemode'=>false,'data'=>['object'=>[
        'mode'=>'payment','status'=>'complete','payment_status'=>'paid','id'=>$lateCheckout,'client_reference_id'=>$lateOrder,
        'metadata'=>['order_id'=>$lateOrder,'user_id'=>(string)$target],'amount_total'=>1700,'currency'=>'aud','payment_intent'=>'pi_deletion_late_fixture']]];
    $settled=nisha_commerce_apply_event(db(),$lateEvent);
    $ledger=db()->query("SELECT user_id,amount_minor,status FROM payment_orders WHERE id='$lateOrder'")->fetch();
    md_ok($settled['received']&&$ledger['status']==='paid'&&(int)$ledger['user_id']===$target&&(int)$ledger['amount_minor']===1700,'A late bound settlement records the original financial agreement after anonymisation.');
    md_ok(nisha_account_login_version(db(),$target)===null&&md_count('mobile_access_tokens',$target)===0&&db()->query("SELECT email FROM users WHERE id=$target")->fetchColumn()==='closed-'.$target.'@example.invalid','Financial settlement does not restore identity, account access or native tokens.');
    md_reject(fn()=>nisha_commerce_reserve_mock(db(),$target,'ielts-academic-01','late-settlement-00001'),'A settled payment cannot let the closed account reserve a new mock.');
    md_ok(md_count('exam_runs',$target)===0&&md_count('ai_reviews',$target)===0&&md_count('coaching_watch_sessions',$target)===0,'Late settlement cannot recreate private learning history.');
    md_ok(!empty(nisha_commerce_apply_event(db(),$lateEvent)['duplicate'])&&md_count('payment_orders',$target)===2,'A replay remains idempotent while preserving both original order references.');
    echo "PASS: $checks mobile deletion and anonymisation integrity checks. Isolated local database; no production account removed.\n";
}catch(Throwable$e){$failed=true;fwrite(STDERR,$e instanceof LogicException&&str_starts_with($e->getMessage(),'TEST: ')?$e->getMessage()."\n":'Mobile deletion fixture failed safely ('.get_class($e).").\n");}
finally{if($created)try{md_sql("DROP DATABASE IF EXISTS `$database`; DROP USER IF EXISTS '$dbUser'@'127.0.0.1';");}catch(Throwable$e){$failed=true;fwrite(STDERR,"Deletion fixture cleanup failed.\n");}if(session_status()===PHP_SESSION_ACTIVE)session_write_close();foreach(glob($scratch.'/sessions/*')?:[]as$f)unlink($f);if(is_file($scratch.'/config/config.php'))unlink($scratch.'/config/config.php');foreach(['sessions','config','']as$dir)if(is_dir($scratch.'/'.$dir))rmdir($scratch.'/'.$dir);}
exit($failed?1:0);
