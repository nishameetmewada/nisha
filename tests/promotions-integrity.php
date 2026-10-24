<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
if (($argv[1] ?? '') === '--claim') {
    define('NISHA_ROOT', $argv[2]); ini_set('session.save_path', $argv[2] . '/sessions');
    require $root . '/includes/promotions.php';
    file_put_contents($argv[5], 'ready');
    try {
        $result = nisha_commerce_checkout(db(), (int) $argv[3], static function(array $request): array {
            parse_str($request['body'], $fields); $id = $fields['client_reference_id'];
            return ['status'=>200,'body'=>json_encode(['id'=>'cs_test_fixture_' . $id,'url'=>'https://checkout.stripe.com/c/pay/' . $id])];
        }, $argv[4]);
        echo json_encode(['success'=>true,'order_id'=>$result['order_id']]);
    } catch (NishaCommerceException $exception) { echo json_encode(['success'=>false,'code'=>$exception->errorCode]); }
    catch (Throwable $exception) {
        // Class/driver code aid a concurrency failure without exposing query
        // text or the disposable fixture's credentials.
        $cause=$exception instanceof PDOException ? $exception : $exception->getPrevious();
        echo json_encode(['success'=>false,'unexpected'=>get_class($exception),'driver'=>$cause instanceof PDOException ? (int)($cause->errorInfo[1]??0) : 0,
            'configured'=>config('db_name')!==''&&config('db_user')!=='','mysql'=>extension_loaded('pdo_mysql'),'config_file'=>is_file(NISHA_ROOT.'/config/config.php'),
            'location'=>basename($exception->getFile()).':'.$exception->getLine()]);
    }
    exit;
}
$suffix = bin2hex(random_bytes(7)); $database = 'nisha_promotions_' . $suffix; $databaseUser = 'nisha_promo_' . $suffix;
$databasePassword = bin2hex(random_bytes(24)); $scratch = sys_get_temp_dir() . '/nisha-promotions-' . $suffix;
$admin = null; $checks = 0; $failed = false; $workers = [];
function promo_check(bool $condition, string $label): void { global $checks; if (!$condition) throw new LogicException('Promotion test: ' . $label); ++$checks; }
function promo_throws(callable $call, string $code, string $label): void { try { $call(); } catch (NishaCommerceException $exception) { promo_check($exception->errorCode === $code, $label); return; } throw new LogicException('Promotion test: ' . $label); }
function promo_invalid(callable $call, string $label): void { try { $call(); } catch (InvalidArgumentException|NishaCommerceException $exception) { promo_check(true, $label); return; } throw new LogicException('Promotion test: ' . $label); }
function promo_input(string $code, array $changes = []): array { return array_replace(['code'=>$code,'label'=>'Fixture offer','type'=>'percent','percent'=>'25','currency'=>'aud','enabled'=>'1','featured'=>'1','max_redemptions'=>'','max_per_student'=>'1'], $changes); }
function promo_row(PDO $pdo, string $sql, array $values): array { $q=$pdo->prepare($sql);$q->execute($values);return$q->fetch()?:[]; }
function promo_transport(array $request): array { parse_str($request['body'],$fields);$id=$fields['client_reference_id'];return['status'=>200,'body'=>json_encode(['id'=>'cs_test_fixture_'.$id,'url'=>'https://checkout.stripe.com/c/pay/'.$id])]; }
function promo_event(array $order, string $type = 'checkout.session.completed'): array {
    return ['id'=>'evt_fixture_' . str_replace('.','_',$type) . '_' . $order['id'],'type'=>$type,'livemode'=>false,'data'=>['object'=>[
        'id'=>$order['checkout_id'],'mode'=>'payment','status'=>$type==='checkout.session.expired'?'expired':'complete','payment_status'=>$type==='checkout.session.completed'?'paid':'unpaid',
        'metadata'=>['order_id'=>$order['id'],'user_id'=>(string)$order['user_id']],'client_reference_id'=>$order['id'],'currency'=>$order['currency'],'amount_total'=>(int)$order['amount_minor'],'payment_intent'=>'pi_fixture_'.$order['id']]]];
}
try {
    $admin = new PDO('mysql:unix_socket=/workspace/.tools/mysql-run/mysql.sock;charset=utf8mb4','agent','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $account=$admin->quote($databaseUser)."@'127.0.0.1'";$admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($databasePassword));$admin->exec("GRANT SELECT,INSERT,UPDATE,DELETE ON `$database`.* TO ".$account);
    $admin->exec("USE `$database`");$admin->exec((string)file_get_contents($root.'/database/schema.sql'));$admin->exec((string)file_get_contents($root.'/database/migrations/007_promotions.sql'));
    mkdir($scratch,0700);mkdir($scratch.'/config',0700);mkdir($scratch.'/sessions',0700);
    $config=['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>$database,'db_user'=>$databaseUser,'db_password'=>$databasePassword,'admin_user_ids'=>[1],
        'site_url'=>'https://nisha.example','stripe_secret_key'=>'sk_test_local_fixture','stripe_webhook_secret'=>'whsec_local_fixture','payments_enabled'=>true];
    file_put_contents($scratch.'/config/config.php','<?php return '.var_export($config,true).';');chmod($scratch.'/config/config.php',0600);
    define('NISHA_ROOT',$scratch);ini_set('session.save_path',$scratch.'/sessions');require$root.'/includes/promotions.php';
    $pdo=db();for($id=1;$id<=15;++$id)$pdo->prepare('INSERT INTO users(id,name,email,password_hash) VALUES(?,?,?,?)')->execute([$id,'Promotion fixture '.$id,'promotion-'.$id.'@example.test',password_hash('fixture-only-password',PASSWORD_DEFAULT)]);
    $owner=['id'=>1];$settings=nisha_commerce_settings();
    promo_check($settings['enabled']&&$settings['amount_minor']===2900,'Fixture checkout is enabled with server AUD29 price.');
    promo_throws(fn()=>nisha_promotions_save($pdo,['id'=>2],promo_input('DENIED')),'owner_required','Students cannot create promotions.');
    $promotion=nisha_promotions_save($pdo,$owner,promo_input('SAVE25',['max_redemptions'=>'2']));
    $quote=nisha_promotions_quote($pdo,2,' save25 ',$settings);
    promo_check($quote['amount_minor']===2175&&$quote['discount_minor']===725&&$quote['base_amount_minor']===2900,'Percentage uses server price and integer minor-unit arithmetic.');
    promo_check(nisha_promotions_featured($pdo)['code']==='SAVE25','Active featured promotion is available for a public banner.');
    promo_check((int)$pdo->query('SELECT COUNT(*) FROM commerce_promotion_orders')->fetchColumn()===0,'Preview quotation does not reserve a code.');
    foreach ([['percent'=>'100'],['percent'=>'0'],['type'=>'fixed','fixed_amount'=>'0'],['starts_at'=>'2026-02-30T12:00'],['starts_at'=>'2026-01-02T00:00','ends_at'=>'2026-01-01T00:00'],['max_redemptions'=>'0'],['currency'=>'jpy'],['code'=>'<script>']] as $changes) promo_invalid(fn()=>nisha_promotions_save($pdo,$owner,promo_input('BADCODE',$changes)),'Invalid owner settings are rejected.');
    promo_invalid(fn()=>nisha_promotions_save($pdo,$owner,promo_input('SAVE25')),'Duplicate codes cannot replace an existing offer.');
    promo_check((int)$pdo->query('SELECT COUNT(*) FROM commerce_promotions_audit')->fetchColumn()===1,'Only successful owner creation is audited.');
    $firstFields=[];
    $checkout=nisha_commerce_checkout($pdo,2,static function(array$request)use(&$firstFields):array{parse_str($request['body'],$firstFields);return promo_transport($request);},'SAVE25');
    $order=promo_row($pdo,'SELECT * FROM payment_orders WHERE id=?',[$checkout['order_id']]);
    promo_check((int)$order['amount_minor']===2175&&(int)$firstFields['line_items'][0]['price_data']['unit_amount']===2175&&!isset($firstFields['discounts']),'Stripe receives the authoritative already-discounted line item.');
    $snapshot=nisha_promotions_order_snapshot($pdo,$order['id']);
    promo_check((int)$snapshot['base_amount_minor']===2900&&(int)$snapshot['discount_minor']===725&&$snapshot['code']==='SAVE25','Order stores its immutable full price and promotion snapshot.');
    promo_check(nisha_commerce_checkout($pdo,2,static function():array{throw new LogicException('Unexpected duplicate provider request.');},'SAVE25')['order_id']===$order['id'],'Repeated checkout resumes the same session without another reservation.');
    promo_throws(fn()=>nisha_commerce_checkout($pdo,2,'promo_transport',''),'checkout_promotion_conflict','Changing a pending checkout code cannot silently change its price.');
    nisha_promotions_save($pdo,$owner,promo_input('SAVE25',['id'=>(string)$promotion,'percent'=>'10','enabled'=>'0','max_redemptions'=>'1']));
    promo_check(nisha_commerce_checkout($pdo,2,'promo_transport','SAVE25')['order_id']===$order['id'],'An issued checkout honors its frozen price after the offer changes.');
    promo_throws(fn()=>nisha_promotions_quote($pdo,3,'SAVE25',$settings),'promotion_unavailable','Disabled offers reject new quotations.');
    $wrong=promo_event($order);$wrong['data']['object']['amount_total']=2900;
    promo_throws(fn()=>nisha_commerce_apply_event($pdo,$wrong),'webhook_binding','Webhook must match the discounted charge, not the old list price.');
    $paid=promo_event($order);nisha_commerce_apply_event($pdo,$paid);
    promo_check(nisha_commerce_has_paid($pdo,2)&&nisha_promotions_order_snapshot($pdo,$order['id'])['status']==='redeemed','Signed exact settlement grants access and redeems the reservation.');
    promo_check(nisha_commerce_apply_event($pdo,$paid)['duplicate']===true,'Settlement replay is idempotent.');
    $refund=['id'=>'evt_fixture_refund_'.$order['id'],'type'=>'charge.refunded','livemode'=>false,'data'=>['object'=>['payment_intent'=>$paid['data']['object']['payment_intent'],'currency'=>'aud','amount_refunded'=>2175,'refunds'=>['data'=>[['id'=>'re_fixture_'.$order['id'],'currency'=>'aud','amount'=>2175,'status'=>'succeeded']]]]]];
    nisha_commerce_apply_event($pdo,$refund);
    promo_check(!nisha_commerce_has_paid($pdo,2)&&nisha_promotions_order_snapshot($pdo,$order['id'])['status']==='redeemed','Refunds revoke funded access without recycling promotional usage.');
    promo_check((int)$pdo->query("SELECT SUM(amount_minor) FROM payment_orders WHERE paid_at IS NOT NULL")->fetchColumn()===2175,'Financial revenue records actual discounted receipts.');

    $fixed=nisha_promotions_save($pdo,$owner,promo_input('FIXED5',['type'=>'fixed','fixed_amount'=>'5.00']));
    promo_check(nisha_promotions_quote($pdo,3,'FIXED5',$settings)['amount_minor']===2400,'Fixed discount subtracts the exact currency amount.');
    $otherCurrency=$settings;$otherCurrency['currency']='usd';
    promo_throws(fn()=>nisha_promotions_quote($pdo,3,'FIXED5',$otherCurrency),'promotion_unavailable','Fixed offers cannot cross currencies.');
    $tooMuch=nisha_promotions_save($pdo,$owner,promo_input('TOOBIG',['type'=>'fixed','fixed_amount'=>'28.01']));
    promo_throws(fn()=>nisha_promotions_quote($pdo,3,'TOOBIG',$settings),'promotion_price','A discount cannot produce a subminimum or free charge.');
    nisha_promotions_save($pdo,$owner,promo_input('FUTURE',['starts_at'=>'2099-01-01T00:00']));
    nisha_promotions_save($pdo,$owner,promo_input('EXPIRED',['ends_at'=>'2001-01-01T00:00']));
    foreach(['FUTURE','EXPIRED']as$code)promo_throws(fn()=>nisha_promotions_quote($pdo,3,$code,$settings),'promotion_unavailable','UTC validity windows are enforced.');

    $expires=nisha_promotions_save($pdo,$owner,promo_input('EXPIRES',['max_redemptions'=>'1']));
    $expiredCheckout=nisha_commerce_checkout($pdo,3,'promo_transport','EXPIRES');$expiredOrder=promo_row($pdo,'SELECT * FROM payment_orders WHERE id=?',[$expiredCheckout['order_id']]);
    promo_throws(fn()=>nisha_promotions_quote($pdo,4,'EXPIRES',$settings),'promotion_limit','An open checkout reserves global promotional capacity.');
    $spoof=promo_event($expiredOrder,'checkout.session.expired');$spoof['data']['object']['id']='cs_test_wrong';
    promo_throws(fn()=>nisha_commerce_apply_event($pdo,$spoof),'webhook_binding','An unrelated session cannot release another reservation.');
    $expiredEvent=promo_event($expiredOrder,'checkout.session.expired');nisha_commerce_apply_event($pdo,$expiredEvent);
    promo_check(nisha_promotions_order_snapshot($pdo,$expiredOrder['id'])['status']==='released'&&nisha_promotions_quote($pdo,4,'EXPIRES',$settings)['amount_minor']===2175,'A signed expired session releases its unused capacity.');
    promo_check(nisha_commerce_apply_event($pdo,$expiredEvent)['duplicate']===true,'Expiry replay cannot alter usage twice.');
    $replacement=nisha_commerce_checkout($pdo,3,'promo_transport','EXPIRES');
    promo_check($replacement['order_id']!==$expiredOrder['id'],'Confirmed expiry starts a fresh checkout rather than reusing a dead URL.');
    $replacementOrder=promo_row($pdo,'SELECT * FROM payment_orders WHERE id=?',[$replacement['order_id']]);nisha_commerce_apply_event($pdo,promo_event($replacementOrder,'checkout.session.async_payment_failed'));
    promo_check(nisha_promotions_order_snapshot($pdo,$replacementOrder['id'])['status']==='released','A signed terminal async failure releases unused capacity.');
    $late=promo_event($expiredOrder);promo_throws(fn()=>nisha_commerce_apply_event($pdo,$late),'webhook_binding','Contradictory settlement after terminal expiry is rejected for owner reconciliation.');

    nisha_promotions_save($pdo,$owner,promo_input('REJECTED',['max_redemptions'=>'1']));
    promo_throws(fn()=>nisha_commerce_checkout($pdo,4,static fn()=>['status'=>400,'body'=>'{"error":{"type":"invalid_request_error","param":"expires_at"}}'],'REJECTED'),'checkout_rejected','Definitive provider validation rejection is distinguished from uncertain transport.');
    $rejected=promo_row($pdo,"SELECT * FROM commerce_promotion_orders WHERE code='REJECTED'",[]);
    promo_check($rejected['status']==='released'&&$rejected['release_reason']==='provider_rejected','A definitely rejected creation does not permanently consume capacity.');
    $retry=nisha_commerce_checkout($pdo,4,'promo_transport','REJECTED');
    promo_check($retry['order_id']!==$rejected['order_id'],'Definitively rejected checkout retries with a fresh immutable order.');
    nisha_promotions_save($pdo,$owner,promo_input('UNCERTAIN',['max_redemptions'=>'1']));
    promo_throws(fn()=>nisha_commerce_checkout($pdo,5,static fn()=>['status'=>500,'body'=>'{}'],'UNCERTAIN'),'checkout_provider','Uncertain provider errors are not treated as confirmed nonpayment.');
    $uncertain=promo_row($pdo,"SELECT * FROM commerce_promotion_orders WHERE code='UNCERTAIN'",[]);
    promo_check($uncertain['status']==='reserved','An uncertain request keeps capacity reserved.');
    $retry=nisha_commerce_checkout($pdo,5,'promo_transport','UNCERTAIN');
    promo_check($retry['order_id']===$uncertain['order_id'],'Unknown provider outcome safely retries the same immutable order/idempotency key.');
    nisha_promotions_save($pdo,$owner,promo_input('PERUSER',['max_per_student'=>'1']));
    $peruserCheckout=nisha_commerce_checkout($pdo,6,'promo_transport','PERUSER');$peruserOrder=promo_row($pdo,'SELECT * FROM payment_orders WHERE id=?',[$peruserCheckout['order_id']]);nisha_commerce_apply_event($pdo,promo_event($peruserOrder));
    promo_throws(fn()=>nisha_promotions_quote($pdo,6,'PERUSER',$settings),'promotion_limit','Redeemed per-student capacity remains consumed.');
    promo_check(nisha_promotions_quote($pdo,7,'PERUSER',$settings)['amount_minor']===2175,'A student-specific limit does not block other students.');

    $concurrent=nisha_promotions_save($pdo,$owner,promo_input('ONELEFT',['max_redemptions'=>'1']));
    $admin->beginTransaction();$admin->query('SELECT id FROM commerce_promotions WHERE id='.(int)$concurrent.' FOR UPDATE')->fetch();
    // Reuse the parent runtime configuration: prepared environments may load
    // PDO/MySQL through an explicit php.ini rather than the binary's defaults.
    $workerCommand=[PHP_BINARY];$runtimeIni=php_ini_loaded_file();if($runtimeIni!==false)array_push($workerCommand,'-c',$runtimeIni);
    foreach([8,9]as$userId){$ready=$scratch.'/ready-'.$userId;$process=proc_open([...$workerCommand,__FILE__,'--claim',$scratch,(string)$userId,'ONELEFT',$ready],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Could not start concurrency fixture.');fclose($pipes[0]);$workers[]=['process'=>$process,'pipes'=>$pipes,'ready'=>$ready];}
    $deadline=microtime(true)+5;do{$readyCount=count(array_filter($workers,static fn(array$worker):bool=>is_file($worker['ready'])));if($readyCount===2)break;usleep(10000);}while(microtime(true)<$deadline);
    promo_check($readyCount===2,'Both independent checkout workers start before the promotion lock is released.');usleep(150000);$admin->commit();
    $results=[];foreach($workers as&$worker){$output=stream_get_contents($worker['pipes'][1]);fclose($worker['pipes'][1]);$error=stream_get_contents($worker['pipes'][2]);fclose($worker['pipes'][2]);$exit=proc_close($worker['process']);$worker['process']=null;promo_check($exit===0&&$error==='','Concurrent checkout fixture completes without PHP or database errors.');$result=json_decode($output,true,8,JSON_THROW_ON_ERROR);if(isset($result['unexpected']))throw new LogicException('Concurrent checkout error: '.json_encode($result));$results[]=$result;}unset($worker);
    promo_check(count(array_filter($results,static fn(array$result):bool=>$result['success']))===1&&count(array_filter($results,static fn(array$result):bool=>!$result['success']&&$result['code']==='promotion_limit'))===1,'Concurrent students cannot over-reserve the last promotional use.');
    promo_check((int)$pdo->query("SELECT COUNT(*) FROM commerce_promotion_orders WHERE code='ONELEFT' AND status='reserved'")->fetchColumn()===1,'The persisted ledger contains exactly one successful final-slot reservation.');
    promo_check(count(nisha_promotions_list($pdo,$owner))>=10,'Owner listing includes saved promotions and reserved/redeemed usage totals.');
    nisha_promotions_set_enabled($pdo,$owner,$fixed,false);promo_throws(fn()=>nisha_promotions_quote($pdo,7,'FIXED5',$settings),'promotion_unavailable','Owner can disable new uses without changing issued prices.');
    echo 'PASS: '.$checks.' promotion checks. Isolated local database, explicit fake Stripe only; no real charge or payout.' . PHP_EOL;
}catch(Throwable$exception){fwrite(STDERR,$exception instanceof LogicException?$exception->getMessage().PHP_EOL:'Promotion fixture failed safely ('.get_class($exception).').'.PHP_EOL);$failed=true;}
finally{
    if($admin&&$admin->inTransaction())$admin->rollBack();foreach($workers as$worker)if(is_resource($worker['process']??null)){proc_terminate($worker['process']);proc_close($worker['process']);}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    if($admin){try{$admin->exec("DROP DATABASE IF EXISTS `$database`");$admin->exec('DROP USER IF EXISTS '.$admin->quote($databaseUser)."@'127.0.0.1'");}catch(Throwable$exception){fwrite(STDERR,"Disposable promotion database cleanup failed.\n");$failed=true;}}
    foreach(glob($scratch.'/sessions/*')?:[]as$file)unlink($file);foreach(glob($scratch.'/ready-*')?:[]as$file)unlink($file);if(is_file($scratch.'/config/config.php'))unlink($scratch.'/config/config.php');foreach(['sessions','config','']as$directory)if(is_dir($scratch.'/'.$directory))rmdir($scratch.'/'.$directory);
}
exit($failed?1:0);
