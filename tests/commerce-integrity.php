<?php
declare(strict_types=1);

// Disposable local database, explicit fake Stripe transport. No paid calls.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$suffix = bin2hex(random_bytes(7));
$database = 'nisha_commerce_test_' . $suffix;
$databaseUser = 'nisha_commerce_' . $suffix;
$databasePassword = bin2hex(random_bytes(24));
$scratch = sys_get_temp_dir() . '/nisha-commerce-' . $suffix;
$admin = null;
$checks = 0;
$phase = 'disposable local database setup';
$failed = false;
function commerce_check(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $description . "\n"); throw new RuntimeException('Assertion failed.'); }
    ++$checks;
}
function commerce_throws(callable $run, string $code, string $description): void
{
    try { $run(); } catch (NishaCommerceException $exception) { commerce_check($exception->errorCode === $code, $description); return; }
    commerce_check(false, $description);
}
function commerce_row(PDO $pdo, string $query, array $parameters = []): array
{
    $statement = $pdo->prepare($query); $statement->execute($parameters); return $statement->fetch() ?: [];
}
function commerce_save_result(PDO $pdo, int $userId, string $exam, string $attempt, string $type = 'full'): void
{
    $statement = $pdo->prepare('INSERT INTO exam_runs (user_id,exam_id,attempt_id,exam_name,exam,responses_json,result_json,objective_score,objective_total,elapsed_seconds,assisted,completed_sections_json) VALUES (?,?,?,\'Fixture mock\',\'IELTS\',\'{}\',?,0,1,1,0,\'[]\')');
    $statement->execute([$userId,$exam,$attempt,json_encode(['session_type'=>$type])]);
}
function commerce_paid_event(array $order, string $suffix = ''): array
{
    return ['id'=>'evt_fixture_' . $order['id'] . $suffix,'type'=>'checkout.session.completed','livemode'=>false,'data'=>['object'=>[
        'mode'=>'payment','status'=>'complete','payment_status'=>'paid','id'=>$order['checkout_id'],'client_reference_id'=>$order['id'],
        'metadata'=>['order_id'=>$order['id'],'user_id'=>(string)$order['user_id']],'amount_total'=>(int)$order['amount_minor'],
        'currency'=>$order['currency'],'payment_intent'=>'pi_fixture_' . $order['id']]]];
}
function commerce_fixture_order(PDO $pdo, int $userId, int $days = 30): array
{
    $id = bin2hex(random_bytes(16));
    $statement = $pdo->prepare('INSERT INTO payment_orders (id,user_id,amount_minor,currency,access_days,checkout_id) VALUES (?,?,2900,\'aud\',?,?)');
    $statement->execute([$id,$userId,$days,'cs_test_fixture_' . $id]);
    return commerce_row($pdo,'SELECT * FROM payment_orders WHERE id=?',[$id]);
}
try {
    $socket = '/workspace/.tools/mysql-run/mysql.sock';
    if (!is_file('/workspace/.tools/bin/mariadb') || !file_exists($socket)) throw new RuntimeException('Prepared local MariaDB runtime is required.');
    $admin = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'agent', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $account = $admin->quote($databaseUser) . "@'127.0.0.1'";
    $admin->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $admin->quote($databasePassword));
    $admin->exec("GRANT SELECT,INSERT,UPDATE,DELETE ON `$database`.* TO " . $account);
    $admin->exec("USE `$database`");
    $admin->exec((string) file_get_contents($root . '/database/schema.sql'));
    $admin->exec((string) file_get_contents($root . '/database/migrations/004_commerce.sql'));
    mkdir($scratch,0700); mkdir($scratch . '/config',0700); mkdir($scratch . '/sessions',0700);
    $configuration = ['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>$database,'db_user'=>$databaseUser,'db_password'=>$databasePassword,
        'admin_user_ids'=>[1],'site_url'=>'https://nisha.example','stripe_secret_key'=>'sk_test_nisha_local_fixture',
        'stripe_webhook_secret'=>'whsec_nisha_local_fixture','payments_enabled'=>false];
    file_put_contents($scratch . '/config/config.php','<?php return ' . var_export($configuration,true) . ';'); chmod($scratch . '/config/config.php',0600);
    ini_set('session.save_path',$scratch . '/sessions');
    define('NISHA_ROOT',$scratch);
    require $root . '/includes/teachers.php';
    $pdo = db();
    for ($id=1;$id<=7;++$id) {
        $statement = $pdo->prepare('INSERT INTO users(id,name,email,password_hash) VALUES (?,?,?,?)');
        $statement->execute([$id,'Commerce fixture ' . $id,'commerce-' . $id . '@example.test',password_hash('disposable-only-password',PASSWORD_DEFAULT)]);
    }
    $owner = ['id'=>1];
    $phase = 'owner billing controls';
    $settings = nisha_commerce_settings();
    commerce_check($settings['amount_minor']===2900 && $settings['currency']==='aud' && $settings['access_days']===30 && !$settings['enabled'],'Launch defaults are AUD29 for30days and disabled checkout.');
    commerce_throws(static fn()=>nisha_commerce_save_plan($pdo,['id'=>2],['amount'=>'29.00','currency'=>'aud','access_days'=>'30']), 'owner_required','Students cannot change billing settings.');
    foreach ([['amount'=>'2.999','currency'=>'aud','access_days'=>'30'],['amount'=>'29','currency'=>'jpy','access_days'=>'30'],['amount'=>'29','currency'=>'aud','access_days'=>'0']] as $invalid) {
        try { nisha_commerce_save_plan($pdo,$owner,$invalid); commerce_check(false,'Invalid price, currency or duration is rejected.'); }
        catch (InvalidArgumentException $exception) { commerce_check(true,'Invalid owner billing value is rejected.'); }
    }
    putenv('NISHA_STRIPE_WEBHOOK_SECRET=invalid');
    try { nisha_commerce_save_plan($pdo,$owner,['amount'=>'29','currency'=>'aud','access_days'=>'30','checkout_enabled'=>'1']); commerce_check(false,'Checkout cannot be enabled without private signing setup.'); }
    catch (InvalidArgumentException $exception) { commerce_check(true,'Missing private signing setup prevents enabling checkout.'); }
    putenv('NISHA_STRIPE_WEBHOOK_SECRET');
    nisha_commerce_save_plan($pdo,$owner,['amount'=>'29.00','currency'=>'aud','access_days'=>'30','checkout_enabled'=>'1']);
    commerce_check(nisha_commerce_settings()['enabled'] && (int)$pdo->query('SELECT COUNT(*) FROM commerce_settings_audit')->fetchColumn()===1,'Owner changes are applied and recorded in the audit ledger.');

    $phase = 'trial reservation and completion';
    $attempt = 'trial-' . bin2hex(random_bytes(10));
    commerce_check(nisha_commerce_overview($pdo,2)['trial_state']==='available','New student receives one free full mock.');
    commerce_check(nisha_commerce_reserve_mock($pdo,2,'ielts-academic-01',$attempt)['kind']==='trial','First full mock reserves the trial.');
    nisha_commerce_reserve_mock($pdo,2,'ielts-academic-01',$attempt);
    commerce_check((int)$pdo->query('SELECT COUNT(*) FROM mock_access_grants WHERE user_id=2')->fetchColumn()===1,'Same free attempt resumes idempotently.');
    commerce_throws(static fn()=>nisha_commerce_reserve_mock($pdo,2,'pte-core-01','trial-parallel-' . str_repeat('a',16)), 'mock_trial_reserved','A second parallel attempt cannot reserve another free mock.');
    commerce_throws(static fn()=>nisha_commerce_reserve_mock($pdo,2,'pte-core-01',$attempt),'mock_attempt_conflict','Attempt references cannot switch full papers.');
    commerce_throws(static fn()=>nisha_commerce_assert_mock($pdo,3,'ielts-academic-01',$attempt),'mock_access_required','Another student cannot reuse an access grant.');
    commerce_throws(static fn()=>nisha_commerce_finish_mock($pdo,2,'ielts-academic-01',$attempt),'mock_result_not_saved','An unsaved attempt cannot consume a trial.');
    commerce_save_result($pdo,2,'ielts-academic-01',$attempt);
    nisha_commerce_finish_mock($pdo,2,'ielts-academic-01',$attempt);
    nisha_commerce_finish_mock($pdo,2,'ielts-academic-01',$attempt);
    commerce_check(nisha_commerce_overview($pdo,2)['trial_state']==='consumed','Saved completion consumes the free mock once.');
    commerce_throws(static fn()=>nisha_commerce_reserve_mock($pdo,2,'pte-core-01','another-' . str_repeat('b',20)), 'mock_payment_required','The next full mock requires paid access.');
    nisha_commerce_assert_mock($pdo,2,'ielts-academic-01',$attempt);
    commerce_check(true,'The original completed result remains readable.');
    commerce_save_result($pdo,4,'ielts-academic-01','historical-' . str_repeat('h',16));
    commerce_check(nisha_commerce_overview($pdo,4)['trial_state']==='consumed','A full mock saved before upgrade counts as the free mock.');
    commerce_save_result($pdo,5,'practice.ielts-academic.reading','practice-' . str_repeat('p',16),'practice');
    commerce_check(nisha_commerce_overview($pdo,5)['trial_state']==='available','Guided sessions do not consume a full-mock trial.');

    $phase = 'parallel trial claims';
    $workers=[];
    $pdo->beginTransaction();
    nisha_commerce_lock_user($pdo,6);
    $workerCode='define("NISHA_ROOT",$argv[1]);require $argv[2];try{$p=db();file_put_contents($argv[3],"ready");while(!is_file($argv[4]))usleep(1000);$g=nisha_commerce_reserve_mock($p,6,"pte-core-01",$argv[5]);echo $g["kind"];}catch(NishaCommerceException $e){echo $e->errorCode;}catch(Throwable $e){echo "fixture_error";exit(1);}';
    foreach ([1,2] as $worker) {
        $command=[PHP_BINARY,'-c',php_ini_loaded_file(),'-d','session.save_path=' . $scratch . '/sessions','-r',$workerCode,$scratch,$root . '/includes/commerce.php',$scratch . '/ready-' . $worker,$scratch . '/go','parallel-' . $worker . '-' . str_repeat('x',16)];
        $process=proc_open($command,[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Parallel fixture process unavailable.');
        fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $deadline=microtime(true)+5;
    while((!is_file($scratch . '/ready-1')||!is_file($scratch . '/ready-2'))&&microtime(true)<$deadline)usleep(1000);
    file_put_contents($scratch . '/go','go');usleep(50000);$pdo->commit();
    $outcomes=[];
    foreach($workers as[$process,$pipes]){$outcomes[]=stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);commerce_check(proc_close($process)===0,'A parallel local trial worker completed safely.');}
    sort($outcomes);
    commerce_check($outcomes===['mock_trial_reserved','trial'] && (int)$pdo->query('SELECT COUNT(*) FROM mock_access_grants WHERE user_id=6')->fetchColumn()===1,'Concurrent database connections can reserve only one free attempt.');

    $phase = 'checkout trust boundary';
    $providerCalls=0; $providerPayload=null;
    $transport=static function(array $request) use (&$providerCalls,&$providerPayload): array {
        ++$providerCalls; parse_str($request['body'],$providerPayload);
        commerce_check($request['url']==='https://api.stripe.com/v1/checkout/sessions','Checkout uses a fixed provider destination.');
        $id='cs_test_fixture_' . $providerPayload['client_reference_id'];
        return ['status'=>200,'body'=>json_encode(['id'=>$id,'url'=>'https://checkout.stripe.com/c/pay/' . $id])];
    };
    $checkout=nisha_commerce_checkout($pdo,2,$transport);
    commerce_check(!$pdo->inTransaction(),'No database transaction is held while opening hosted checkout.');
    commerce_check($providerPayload['mode']==='payment' && (int)$providerPayload['line_items'][0]['price_data']['unit_amount']===2900 && $providerPayload['line_items'][0]['price_data']['currency']==='aud','Price, currency and one-time payment mode come from server settings.');
    commerce_check(!nisha_commerce_has_paid($pdo,2),'Opening checkout does not grant paid access.');
    $again=nisha_commerce_checkout($pdo,2,$transport);
    commerce_check($again===$checkout && $providerCalls===1,'A repeated checkout uses the same existing order and provider session.');
    $order=commerce_row($pdo,'SELECT * FROM payment_orders WHERE id=?',[$checkout['order_id']]);
    commerce_check((int)$providerPayload['expires_at']===strtotime($order['created_at'].' UTC')+86400,'Stripe session expiry and local order reuse share the same immutable24hour deadline.');
    nisha_commerce_save_plan($pdo,$owner,['amount'=>'31','currency'=>'aud','access_days'=>'14','checkout_enabled'=>'1']);
    commerce_check(nisha_commerce_checkout($pdo,2,$transport)===$checkout && $providerCalls===1,'A price change does not create a second payable checkout for the same student.');
    $event=commerce_paid_event($order);
    $raw=json_encode($event,JSON_THROW_ON_ERROR); $now=time(); $secret='whsec_nisha_local_fixture';
    $signature='t=' . $now . ',v1=' . hash_hmac('sha256',$now . '.' . $raw,$secret);
    commerce_check(nisha_stripe_verify_event($raw,$signature,$secret,$now)['id']===$event['id'],'A correctly signed current payment event is accepted.');
    commerce_throws(static fn()=>nisha_stripe_verify_event($raw . ' ',$signature,$secret,$now),'webhook_signature','Modified payment bodies fail signature checks.');
    commerce_throws(static fn()=>nisha_stripe_verify_event($raw,$signature,$secret,$now+301),'webhook_signature','Expired signatures are rejected.');
    commerce_throws(static fn()=>nisha_stripe_verify_event($raw,$signature,'whsec_wrong_fixture',$now),'webhook_signature','Wrong signing secrets are rejected.');
    foreach (['amount_total'=>1,'currency'=>'usd','client_reference_id'=>str_repeat('f',32),'id'=>'cs_test_wrong_fixture'] as $field=>$value) {
        $bad=$event;$bad['data']['object'][$field]=$value;
        commerce_throws(static fn()=>nisha_commerce_apply_event($pdo,$bad),'webhook_binding','Signed events still require matching order ' . $field . '.');
    }
    $bad=$event;$bad['livemode']=true;
    commerce_throws(static fn()=>nisha_commerce_apply_event($pdo,$bad),'webhook_mode','Test events cannot be confused with live payments.');
    nisha_commerce_save_plan($pdo,$owner,['amount'=>'31','currency'=>'aud','access_days'=>'14']);
    commerce_check(!nisha_commerce_settings()['enabled'],'New checkout can be paused.');
    nisha_commerce_apply_event($pdo,$event);
    commerce_check(nisha_commerce_has_paid($pdo,2),'Already paid signed events are processed while new checkout is paused.');
    $entitlement=commerce_row($pdo,'SELECT * FROM student_entitlements WHERE order_id=?',[$order['id']]);
    commerce_check(strtotime($entitlement['expires_at'].' UTC')-strtotime($entitlement['starts_at'].' UTC')===30*86400,'A pending order retains its original30day agreement after price changes.');
    commerce_check(nisha_commerce_apply_event($pdo,$event)['duplicate']===true,'Repeated signed events are idempotent.');
    $repeat=$event;$repeat['id'].='_repeat';nisha_commerce_apply_event($pdo,$repeat);
    commerce_check((int)$pdo->query('SELECT COUNT(*) FROM student_entitlements WHERE user_id=2')->fetchColumn()===1,'A different event for the same settled order cannot duplicate access.');
    nisha_commerce_save_plan($pdo,$owner,['amount'=>'29','currency'=>'aud','access_days'=>'30','checkout_enabled'=>'1']);
    commerce_throws(static fn()=>nisha_commerce_checkout($pdo,2,$transport),'pass_already_active','A student with an active pass cannot accidentally buy it again.');

    $phase = 'refunds, expiry grace and funding isolation';
    $paidAttempt='paid-' . str_repeat('r',20);
    nisha_commerce_reserve_mock($pdo,2,'pte-core-01',$paidAttempt);
    commerce_check(commerce_row($pdo,'SELECT order_id FROM mock_paid_sources WHERE user_id=2 AND attempt_id=?',[$paidAttempt])['order_id']===$order['id'],'Paid mock access records its funding purchase.');
    $pdo->prepare('UPDATE student_entitlements SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE order_id=?')->execute([$order['id']]);
    nisha_commerce_assert_mock($pdo,2,'pte-core-01',$paidAttempt);
    commerce_check(true,'A started paid mock has a24hour grace period after normal pass expiry.');
    $refund=['id'=>'evt_refund_fixture_' . $order['id'],'type'=>'charge.refunded','livemode'=>false,'data'=>['object'=>['payment_intent'=>$event['data']['object']['payment_intent'],'currency'=>'aud','amount_refunded'=>100,'refunds'=>['data'=>[['id'=>'re_fixture_' . $order['id'],'amount'=>100,'currency'=>'aud','status'=>'succeeded']]]]]];
    nisha_commerce_apply_event($pdo,$refund);
    commerce_throws(static fn()=>nisha_commerce_assert_mock($pdo,2,'pte-core-01',$paidAttempt),'mock_access_required','An explicit refund revokes unfinished paid mock access, unlike natural expiry.');
    commerce_check(!nisha_commerce_has_paid($pdo,2),'A settled partial refund removes that access pass.');
    nisha_commerce_assert_mock($pdo,2,'ielts-academic-01',$attempt);
    commerce_check(true,'Refunds do not erase completed saved reports.');
    $first=commerce_fixture_order($pdo,3);$second=commerce_fixture_order($pdo,3);
    $firstEvent=commerce_paid_event($first);$secondEvent=commerce_paid_event($second);
    nisha_commerce_apply_event($pdo,$firstEvent);nisha_commerce_apply_event($pdo,$secondEvent);
    $secondAccess=commerce_row($pdo,'SELECT * FROM student_entitlements WHERE order_id=?',[$second['id']]);
    commerce_check(strtotime($secondAccess['expires_at'].' UTC')-strtotime($secondAccess['starts_at'].' UTC')===30*86400,'Concurrent legacy purchases cannot inherit extra duration from each other.');
    $otherRefund=$refund;$otherRefund['id']='evt_other_refund_' . $first['id'];$otherRefund['data']['object']['payment_intent']=$firstEvent['data']['object']['payment_intent'];
    $otherRefund['data']['object']['refunds']['data'][0]['id']='re_other_' . $first['id'];
    nisha_commerce_apply_event($pdo,$otherRefund);
    commerce_check(nisha_commerce_has_paid($pdo,3),'Refunding one purchase does not revoke a separately surviving pass.');
    $secondAccess=commerce_row($pdo,'SELECT * FROM student_entitlements WHERE order_id=?',[$second['id']]);
    commerce_check($secondAccess['revoked_at']===null && strtotime($secondAccess['expires_at'].' UTC')<=time()+30*86400,'A surviving pass retains only its own paid duration after another is refunded.');
    $dispute=['id'=>'evt_dispute_' . $second['id'],'type'=>'charge.dispute.created','livemode'=>false,'data'=>['object'=>['payment_intent'=>$secondEvent['data']['object']['payment_intent']]]];
    nisha_commerce_apply_event($pdo,$dispute);
    commerce_check(!nisha_commerce_has_paid($pdo,3),'A signed dispute suspends the associated access.');
    commerce_check($providerCalls===1,'All payment tests used one explicit fake transport and no real provider requests.');
    echo 'PASS: ' . $checks . " commerce, access, billing and signed-webhook checks. Disposable local database; no paid calls.\n";
} catch (Throwable $exception) {
    fwrite(STDERR,'Commerce checks stopped during ' . $phase . ' (' . get_class($exception) . "). Private database details were not printed.\n");
    $failed=true;
} finally {
    if ($admin instanceof PDO) {
        try { $admin->exec("DROP DATABASE IF EXISTS `$database`"); $admin->exec('DROP USER IF EXISTS ' . $admin->quote($databaseUser) . "@'127.0.0.1'"); }
        catch (Throwable $exception) { fwrite(STDERR,"Disposable commerce database cleanup failed.\n"); $failed=true; }
    }
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    foreach (['ready-1','ready-2','go'] as $file) if (is_file($scratch . '/' . $file)) unlink($scratch . '/' . $file);
    foreach (glob($scratch . '/sessions/*') ?: [] as $file) unlink($file);
    if (is_file($scratch . '/config/config.php')) unlink($scratch . '/config/config.php');
    foreach (['sessions','config',''] as $directory) if (is_dir($scratch . '/' . $directory)) rmdir($scratch . '/' . $directory);
}
exit($failed?1:0);
