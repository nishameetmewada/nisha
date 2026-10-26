<?php
declare(strict_types=1);
// Local isolated accounts only; no credentials, payments or AI provider calls.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$suffix=bin2hex(random_bytes(7));$database='nisha_mobile_'.$suffix;$dbUser='mobile_'.$suffix;$fixturePassword=bin2hex(random_bytes(24));
$scratch=sys_get_temp_dir().'/nisha-mobile-'.$suffix;$admin=getenv('NISHA_TEST_MYSQL_ADMIN_BIN')?:'/workspace/.tools/bin/mariadb';$checks=0;$failed=false;$created=false;
function ma_sql(string$sql):void{global$admin;$p=proc_open([$admin],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Local database client unavailable.');fwrite($pipes[0],$sql);fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Local mobile fixture SQL failed.');}
function ma_ok(bool$ok,string$label):void{global$checks;if(!$ok)throw new LogicException('TEST: '.$label);++$checks;}
function ma_reject(callable$callback,string$code,int$status=422):void{try{$callback();}catch(NishaMobileException$e){ma_ok($e->errorCode===$code&&$e->httpStatus===$status,'Expected safe rejection '.$code);return;}throw new LogicException('TEST: Request unexpectedly accepted: '.$code);}
function ma_issue(int$id):array{$p=db();$p->beginTransaction();try{$q=$p->prepare('SELECT id,name,email,created_at FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$reply=nisha_mobile_issue_token($p,$q->fetch(),'Fixture device');$p->commit();return$reply;}catch(Throwable$e){if($p->inTransaction())$p->rollBack();throw$e;}}
try{
    mkdir($scratch,0700);mkdir($scratch.'/config',0700);mkdir($scratch.'/sessions',0700);
    ma_sql("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '$dbUser'@'127.0.0.1' IDENTIFIED BY '$fixturePassword'; GRANT SELECT,INSERT,UPDATE,DELETE ON `$database`.* TO '$dbUser'@'127.0.0.1';");$created=true;
    ma_sql("USE `$database`;\n".file_get_contents($root.'/database/schema.sql'));
    file_put_contents($scratch.'/config/config.php','<?php return '.var_export(['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>$database,'db_user'=>$dbUser,'db_password'=>$fixturePassword],true).';');chmod($scratch.'/config/config.php',0600);
    define('NISHA_ROOT',$scratch);ini_set('session.save_path',$scratch.'/sessions');require$root.'/includes/mobile-auth.php';
    foreach(['https://localhost','capacitor://localhost','https://nishaglobal.com','https://www.nishaglobal.com',null]as$origin)ma_ok(nisha_mobile_origin_allowed($origin),'Allow exact configured app origin.');
    foreach(['http://localhost','null','https://nishaglobal.com.evil.test','https://localhost/path','https://evil.test']as$origin)ma_ok(!nisha_mobile_origin_allowed($origin),'Reject other app origins.');
    putenv('NISHA_MOBILE_DEVELOPMENT_ORIGINS=http://127.0.0.1:5173,https://evil.test,http://localhost:65536,https://localhost:8080/path');
    ma_ok(nisha_mobile_origin_allowed('http://127.0.0.1:5173')&&!nisha_mobile_origin_allowed('https://evil.test')&&!nisha_mobile_origin_allowed('http://localhost:65536'),'Development origin opt-in accepts only valid loopback origins.');putenv('NISHA_MOBILE_DEVELOPMENT_ORIGINS');
    ma_ok(nisha_mobile_transport_allowed(['HTTPS'=>'on','REMOTE_ADDR'=>'198.51.100.1','HTTP_HOST'=>'nishaglobal.com']),'Real HTTPS is accepted.');
    ma_ok(nisha_mobile_transport_allowed(['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8000']),'Local test server may use loopback HTTP.');
    ma_ok(!nisha_mobile_transport_allowed(['HTTP_X_FORWARDED_PROTO'=>'https','REMOTE_ADDR'=>'198.51.100.1','HTTP_HOST'=>'nishaglobal.com']),'Untrusted forwarded header does not enable cleartext authentication.');
    ma_ok(!nisha_mobile_transport_allowed(['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'nishaglobal.com']),'A loopback proxy address alone does not enable public HTTP.');
    // The canonical schema may already include this additive migration.
    ma_sql("USE `$database`; DROP TABLE IF EXISTS mobile_deletion_requests; DROP TABLE IF EXISTS mobile_auth_limits; DROP TABLE IF EXISTS mobile_access_tokens;");
    ma_reject(fn()=>nisha_mobile_ready(db()),'mobile_setup_required',503);
    ma_sql("USE `$database`;\n".file_get_contents($root.'/database/migrations/010_mobile_app.sql'));nisha_mobile_ready(db());ma_ok(true,'Full additive mobile schema is ready.');
    $input=['name'=>'Local Mobile Student','email'=>'  MOBILE@example.test ','password'=>'Mobile-fixture-password-123','password_confirmation'=>'Mobile-fixture-password-123','device_name'=>'Fixture phone'];
    ma_reject(fn()=>nisha_mobile_register(db(),$input+['role'=>'admin'],'register-invalid'),'mobile_request_invalid');
    ma_reject(fn()=>nisha_mobile_register(db(),array_replace($input,['name'=>"Invalid\nName"]),'register-invalid'),'mobile_request_invalid');
    ma_reject(fn()=>nisha_mobile_register(db(),array_replace($input,['password'=>'short','password_confirmation'=>'short']),'register-invalid'),'mobile_password_invalid');
    ma_reject(fn()=>nisha_mobile_register(db(),array_replace($input,['password_confirmation'=>'wrong']),'register-invalid'),'mobile_password_invalid');
    $registered=nisha_mobile_register(db(),$input,'register-valid');$user=$registered['user'];$token=$registered['access_token'];$id=$user['id'];
    ma_ok($registered['success']&&$registered['token_type']==='Bearer'&&preg_match('/\Anm1_[a-f0-9]{64}\z/D',$token)===1,'Registration issues a cryptographically random opaque token.');
    ma_ok($user['email']==='mobile@example.test'&&array_keys($user)===['id','name','email','created_at'],'User DTO contains normalized identity only.');
    $duration=strtotime($registered['expires_at'].' UTC')-time();ma_ok($duration>2591900&&$duration<=2592000,'Issued tokens have a bounded 30-day expiry.');
    $q=db()->prepare('SELECT * FROM mobile_access_tokens WHERE user_id=?');$q->execute([$id]);$stored=$q->fetch();
    ma_ok($stored['token_hash']===hash('sha256',$token)&&!str_contains(json_encode($stored),$token),'Database stores token hash rather than bearer material.');
    ma_ok(!isset($_SESSION['user_id'])&&!isset($user['password_hash']),'Native registration never signs a browser session in or exposes a password hash.');
    ma_ok(nisha_mobile_authenticate(db(),$token)===$user,'A valid bearer returns only its own user.');
    ma_ok(nisha_mobile_bearer(['HTTP_AUTHORIZATION'=>'Bearer '.$token])===$token&&nisha_mobile_bearer(['HTTP_COOKIE'=>'nisha_session=fixture'])===null,'Only the explicit Authorization header carries app identity.');
    ma_reject(fn()=>nisha_mobile_bearer(['HTTP_AUTHORIZATION'=>'Basic '.$token]),'mobile_token_invalid',401);
    ma_reject(fn()=>nisha_mobile_authenticate(db(),'nm1_'.str_repeat('a',64)),'mobile_token_invalid',401);
    ma_reject(fn()=>nisha_mobile_register(db(),$input,'register-valid'),'mobile_email_exists',409);
    $login=['email'=>$user['email'],'password'=>$input['password']];$second=nisha_mobile_login(db(),$login,'login-valid');
    ma_ok($second['access_token']!==$token&&$second['user']===$user,'Fresh sign-in issues a different token for the same account.');
    nisha_mobile_logout(db(),$token);ma_reject(fn()=>nisha_mobile_authenticate(db(),$token),'mobile_token_invalid',401);
    ma_ok(nisha_mobile_authenticate(db(),$second['access_token'])===$user,'Signing out one device leaves another device active.');
    db()->exec("UPDATE mobile_access_tokens SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE user_id=$id AND revoked_at IS NULL");
    ma_reject(fn()=>nisha_mobile_authenticate(db(),$second['access_token']),'mobile_token_invalid',401);
    $devices=[];for($i=0;$i<11;$i++)$devices[]=ma_issue($id);
    $q=db()->prepare('SELECT COUNT(*) FROM mobile_access_tokens WHERE user_id=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP()');$q->execute([$id]);ma_ok((int)$q->fetchColumn()===10,'Remembered active devices are bounded to ten.');
    ma_reject(fn()=>nisha_mobile_authenticate(db(),$devices[0]['access_token']),'mobile_token_invalid',401);
    $latest=end($devices);ma_ok(nisha_mobile_authenticate(db(),$latest['access_token'])===$user,'Newest remembered device survives old-device cleanup.');
    db()->exec("UPDATE account_security SET auth_version=auth_version+1 WHERE user_id=$id");ma_reject(fn()=>nisha_mobile_authenticate(db(),$latest['access_token']),'mobile_token_invalid',401);
    $fresh=ma_issue($id);db()->exec("UPDATE account_security SET status='suspended',auth_version=auth_version+1 WHERE user_id=$id");
    ma_reject(fn()=>nisha_mobile_authenticate(db(),$fresh['access_token']),'mobile_token_invalid',401);
    ma_reject(fn()=>nisha_mobile_login(db(),$login,'login-valid'),'mobile_credentials_invalid',401);
    db()->exec("UPDATE account_security SET status='active',auth_version=auth_version+1 WHERE user_id=$id");ma_reject(fn()=>nisha_mobile_authenticate(db(),$fresh['access_token']),'mobile_token_invalid',401);
    for($i=0;$i<5;$i++)ma_reject(fn()=>nisha_mobile_login(db(),['email'=>'missing@example.test','password'=>'wrong-password'],'login-missing'),'mobile_credentials_invalid',401);
    ma_reject(fn()=>nisha_mobile_login(db(),['email'=>'missing@example.test','password'=>'wrong-password'],'different-ip'),'mobile_rate_limited',429);
    nisha_mobile_rate_limit(db(),['deterministic-fixture'=>1],9000);ma_reject(fn()=>nisha_mobile_rate_limit(db(),['deterministic-fixture'=>1],9001),'mobile_rate_limited',429);nisha_mobile_rate_limit(db(),['deterministic-fixture'=>1],9900);ma_ok(true,'A persisted rate limit releases in the next time window.');
    $latest=ma_issue($id);$before=nisha_account_security(db(),$id)['auth_version'];
    ma_reject(fn()=>nisha_mobile_deletion_request(db(),$user,['password'=>$input['password'],'confirmation'=>false],'deletion-ip'),'mobile_confirmation_required');
    ma_reject(fn()=>nisha_mobile_deletion_request(db(),$user,['password'=>'wrong','confirmation'=>true],'deletion-ip'),'mobile_credentials_invalid',401);
    $deletion=nisha_mobile_deletion_request(db(),$user,['password'=>$input['password'],'confirmation'=>true,'reason'=>'Local test deletion request.'],'deletion-ip');
    ma_ok($deletion['success']&&$deletion['status']==='pending'&&str_contains($deletion['message'],'not completed deletion'),'Deletion endpoint acknowledges a pending owner-fulfilled request honestly.');
    ma_ok(nisha_account_security(db(),$id)['auth_version']===$before+1,'Deletion request invalidates browser session versions.');
    ma_reject(fn()=>nisha_mobile_authenticate(db(),$latest['access_token']),'mobile_token_invalid',401);
    $q=db()->prepare('SELECT COUNT(*) FROM users WHERE id=?');$q->execute([$id]);ma_ok((int)$q->fetchColumn()===1,'A request does not pretend to hard-delete retained account or financial records.');
    $q=db()->prepare('SELECT * FROM mobile_deletion_requests WHERE user_id=?');$q->execute([$id]);$request=$q->fetch();ma_ok($request['reason']==='Local test deletion request.'&&!str_contains(json_encode($request),$input['password']),'Deletion audit stores only reason/status and timestamps, never a password.');
    nisha_mobile_deletion_request(db(),$user,['password'=>$input['password'],'confirmation'=>true],'deletion-ip');$q=db()->prepare('SELECT COUNT(*) FROM mobile_deletion_requests WHERE user_id=?');$q->execute([$id]);ma_ok((int)$q->fetchColumn()===1,'Repeated confirmed requests keep a single owner queue entry.');
    ma_sql("RENAME TABLE `$database`.account_security TO `$database`.account_security_fixture;");ma_reject(fn()=>nisha_mobile_ready(db()),'mobile_setup_required',503);
    echo "PASS: $checks mobile authentication integrity checks. Isolated local database; no provider calls or real app accounts.\n";
}catch(Throwable$e){$failed=true;fwrite(STDERR,$e instanceof LogicException&&str_starts_with($e->getMessage(),'TEST: ')?$e->getMessage()."\n":'Mobile authentication fixture failed safely ('.get_class($e).").\n");}
finally{
    if($created)try{ma_sql("DROP DATABASE IF EXISTS `$database`; DROP USER IF EXISTS '$dbUser'@'127.0.0.1';");}catch(Throwable$e){$failed=true;fwrite(STDERR,"Local mobile fixture cleanup failed.\n");}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    foreach(glob($scratch.'/sessions/*')?:[]as$f)unlink($f);if(is_file($scratch.'/config/config.php'))unlink($scratch.'/config/config.php');
    foreach(['sessions','config','']as$directory)if(is_dir($scratch.'/'.$directory))rmdir($scratch.'/'.$directory);
}
exit($failed?1:0);
