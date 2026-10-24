<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

class NishaCommerceException extends RuntimeException
{
    public function __construct(public string $publicMessage, public string $errorCode = 'commerce_unavailable', public int $httpStatus = 503)
    { parent::__construct($publicMessage); }
}

function nisha_commerce_setting(string $key, string $environment, mixed $default = null): mixed
{
    $value = getenv($environment);
    return $value !== false && trim($value) !== '' ? $value : config($key, $default);
}

/** Non-secret owner settings; absence of the optional migration keeps checkout off. */
function nisha_commerce_plan(?PDO $pdo = null): array
{
    $plan = ['amount_minor' => (int) config('payment_amount_minor', 2900), 'currency' => strtolower((string) config('payment_currency', 'aud')),
        'access_days' => (int) config('payment_access_days', 30), 'checkout_enabled' => filter_var(config('payments_enabled', false), FILTER_VALIDATE_BOOLEAN)];
    try {
        $row = ($pdo ?? db())->query('SELECT amount_minor,currency,access_days,checkout_enabled FROM commerce_settings WHERE id=1')->fetch();
        if ($row) $plan = ['amount_minor' => (int) $row['amount_minor'], 'currency' => $row['currency'], 'access_days' => (int) $row['access_days'], 'checkout_enabled' => (bool) $row['checkout_enabled']];
    } catch (Throwable $exception) { /* The public price remains readable before account setup. */ }
    foreach (['NISHA_PAYMENT_AMOUNT_MINOR' => 'amount_minor', 'NISHA_PAYMENT_CURRENCY' => 'currency', 'NISHA_PAYMENT_ACCESS_DAYS' => 'access_days', 'NISHA_PAYMENTS_ENABLED' => 'checkout_enabled'] as $environment => $field) {
        $value = getenv($environment);
        if ($value !== false && trim($value) !== '') $plan[$field] = $value;
    }
    return $plan;
}

/** Provider credentials and price are server settings, never browser input. */
function nisha_commerce_settings(): array
{
    $plan = nisha_commerce_plan();
    $currency = strtolower(trim((string) $plan['currency']));
    $amount = filter_var($plan['amount_minor'], FILTER_VALIDATE_INT);
    $days = filter_var($plan['access_days'], FILTER_VALIDATE_INT);
    $site = rtrim(trim((string) nisha_commerce_setting('site_url', 'NISHA_SITE_URL', '')), '/');
    $parts = parse_url($site);
    $validSite = is_array($parts) && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment']) && (!isset($parts['port']) || $parts['port'] === 443) && !preg_match('/[\r\n\x00]/', $site);
    $key = trim((string) nisha_commerce_setting('stripe_secret_key', 'NISHA_STRIPE_SECRET_KEY', ''));
    $webhook = trim((string) nisha_commerce_setting('stripe_webhook_secret', 'NISHA_STRIPE_WEBHOOK_SECRET', ''));
    $validKey = preg_match('/\Ask_(test|live)_[A-Za-z0-9_]+\z/D', $key) === 1;
    $validWebhook = preg_match('/\Awhsec_[A-Za-z0-9_]+\z/D', $webhook) === 1;
    $priceReady = is_int($amount) && $amount >= 100 && $amount <= 100000000 && is_int($days) && $days >= 1 && $days <= 3650
        && in_array($currency, ['usd','nzd','aud','gbp','eur','inr','cad','sgd'], true);
    $enabled = filter_var($plan['checkout_enabled'], FILTER_VALIDATE_BOOLEAN);
    return ['enabled' => $enabled && $priceReady && $validSite && $validKey && $validWebhook,
        'checkout_requested' => $enabled, 'provider_ready' => $validSite && $validKey && $validWebhook,
        'price_ready' => $priceReady, 'amount_minor' => $amount ?: 0, 'currency' => $currency, 'access_days' => $days ?: 0,
        'site_url' => $site, 'secret_key' => $key, 'webhook_secret' => $webhook, 'live' => str_starts_with($key, 'sk_live_')];
}

/** The page must authenticate the owner; the helper also enforces that boundary. */
function nisha_commerce_save_plan(PDO $pdo, array $owner, array $input): void
{
    if (!function_exists('nisha_is_admin') || !nisha_is_admin($owner)) throw new NishaCommerceException('Owner access is required.', 'owner_required', 403);
    $amountText = $input['amount'] ?? null;
    if (!is_string($amountText) || !preg_match('/\A(?:[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?\z/D', $amountText)) throw new InvalidArgumentException('Enter a price from 1.00 to 999999.99, using at most two decimal places.');
    $pieces = explode('.', $amountText, 2);
    $amount = (int) $pieces[0] * 100 + (int) str_pad($pieces[1] ?? '', 2, '0');
    $currency = is_string($input['currency'] ?? null) ? strtolower($input['currency']) : '';
    $days = filter_var($input['access_days'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($currency, ['usd','nzd','aud','gbp','eur','inr','cad','sgd'], true) || !is_int($days) || $days < 1 || $days > 3650) throw new InvalidArgumentException('Choose a supported currency and an access period from 1 to 3650 days.');
    $enabled = ($input['checkout_enabled'] ?? '') === '1';
    if ($enabled && !nisha_commerce_settings()['provider_ready']) throw new InvalidArgumentException('Add the private Stripe key, webhook signing secret and HTTPS site URL before enabling checkout.');
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('INSERT INTO commerce_settings (id,amount_minor,currency,access_days,checkout_enabled,updated_by) VALUES (1,?,?,?,?,?) ON DUPLICATE KEY UPDATE amount_minor=VALUES(amount_minor),currency=VALUES(currency),access_days=VALUES(access_days),checkout_enabled=VALUES(checkout_enabled),updated_by=VALUES(updated_by)');
        $statement->execute([$amount,$currency,$days,(int) $enabled,(int) $owner['id']]);
        $statement = $pdo->prepare('INSERT INTO commerce_settings_audit (owner_id,amount_minor,currency,access_days,checkout_enabled) VALUES (?,?,?,?,?)');
        $statement->execute([(int) $owner['id'],$amount,$currency,$days,(int) $enabled]);
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}

function nisha_commerce_money(int $minor, string $currency): string
{ return strtoupper($currency) . ' ' . number_format($minor / 100, 2); }

function nisha_commerce_has_paid(PDO $pdo, int $userId): bool
{
    $q = $pdo->prepare('SELECT 1 FROM student_entitlements WHERE user_id=? AND revoked_at IS NULL AND starts_at<=UTC_TIMESTAMP() AND expires_at>UTC_TIMESTAMP() LIMIT 1');
    $q->execute([$userId]); return (bool) $q->fetchColumn();
}

function nisha_commerce_active_order(PDO $pdo, int $userId): ?string
{
    $query = $pdo->prepare('SELECT order_id FROM student_entitlements WHERE user_id=? AND revoked_at IS NULL AND starts_at<=UTC_TIMESTAMP() AND expires_at>UTC_TIMESTAMP() ORDER BY expires_at DESC,id DESC LIMIT 1');
    $query->execute([$userId]);
    return $query->fetchColumn() ?: null;
}

function nisha_commerce_grant_funding_valid(PDO $pdo, int $userId, string $attemptId): bool
{
    $query = $pdo->prepare('SELECT 1 FROM mock_paid_sources s JOIN student_entitlements e ON e.order_id=s.order_id AND e.user_id=s.user_id WHERE s.user_id=? AND s.attempt_id=? AND e.revoked_at IS NULL LIMIT 1');
    $query->execute([$userId,$attemptId]);
    return (bool) $query->fetchColumn();
}

function nisha_commerce_fund_grant(PDO $pdo, int $userId, string $attemptId, string $orderId): void
{
    $query = $pdo->prepare('INSERT INTO mock_paid_sources (user_id,attempt_id,order_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE order_id=VALUES(order_id)');
    $query->execute([$userId,$attemptId,$orderId]);
}

function nisha_commerce_existing_full(PDO $pdo, int $userId): bool
{
    $q = $pdo->prepare("SELECT 1 FROM exam_runs WHERE user_id=? AND CASE WHEN JSON_VALID(result_json) THEN COALESCE(JSON_UNQUOTE(JSON_EXTRACT(result_json,'$.session_type')),'full') ELSE 'full' END='full' LIMIT 1");
    $q->execute([$userId]); return (bool) $q->fetchColumn();
}

function nisha_commerce_overview(PDO $pdo, int $userId): array
{
    $q = $pdo->prepare('SELECT MAX(expires_at) FROM student_entitlements WHERE user_id=? AND revoked_at IS NULL AND starts_at<=UTC_TIMESTAMP() AND expires_at>UTC_TIMESTAMP()');
    $q->execute([$userId]); $until = $q->fetchColumn() ?: null;
    $q = $pdo->prepare('SELECT status,exam_id,attempt_id FROM commerce_trials WHERE user_id=?'); $q->execute([$userId]); $trial = $q->fetch() ?: [];
    $state = $trial['status'] ?? 'available';
    if ($state !== 'consumed' && nisha_commerce_existing_full($pdo, $userId)) $state = 'consumed';
    return ['paid_active' => $until !== null, 'paid_until' => $until, 'trial_state' => $state,
        'trial_exam_id' => $trial['exam_id'] ?? null, 'trial_attempt_id' => $trial['attempt_id'] ?? null];
}

function nisha_commerce_valid_attempt(string $examId, string $attemptId): void
{
    if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $examId) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{15,79}\z/D', $attemptId)) {
        throw new NishaCommerceException('Choose a valid mock and attempt reference.', 'mock_reference_invalid', 422);
    }
}

/** User row lock serialises trial reservations and payment extensions. */
function nisha_commerce_lock_user(PDO $pdo, int $userId): void
{
    $q = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $q->execute([$userId]);
    if (!$q->fetchColumn()) throw new NishaCommerceException('Sign in to start your mock.', 'login_required', 401);
}

function nisha_commerce_reserve_mock(PDO $pdo, int $userId, string $examId, string $attemptId): array
{
    nisha_commerce_valid_attempt($examId, $attemptId);
    $owns = !$pdo->inTransaction(); if ($owns) $pdo->beginTransaction();
    try {
        nisha_commerce_lock_user($pdo, $userId);
        $q = $pdo->prepare('SELECT *, (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) AS valid_now FROM mock_access_grants WHERE user_id=? AND attempt_id=? FOR UPDATE'); $q->execute([$userId,$attemptId]); $grant = $q->fetch();
        if ($grant) {
            if ($grant['exam_id'] !== $examId) throw new NishaCommerceException('This attempt belongs to a different mock. Start the correct saved attempt.', 'mock_attempt_conflict', 409);
            if (!$grant['finished_at'] && $grant['kind'] === 'paid' && !nisha_commerce_grant_funding_valid($pdo, $userId, $attemptId)) {
                $replacement = nisha_commerce_active_order($pdo, $userId);
                if (!$replacement) throw new NishaCommerceException('The purchase funding this mock was refunded or disputed. An active pass is required to continue.', 'mock_payment_revoked', 402);
                nisha_commerce_fund_grant($pdo, $userId, $attemptId, $replacement);
            }
            if (!$grant['finished_at'] && !(bool)$grant['valid_now']) throw new NishaCommerceException('This mock access window expired. Start a new mock with an active pass.', 'mock_access_expired', 402);
            if ($owns) $pdo->commit(); return ['exam_id'=>$examId,'attempt_id'=>$attemptId,'kind'=>$grant['kind']];
        }
        $paid = nisha_commerce_has_paid($pdo, $userId);
        if (!$paid) {
            $used = nisha_commerce_existing_full($pdo, $userId);
            $q = $pdo->prepare("INSERT IGNORE INTO commerce_trials (user_id,status) VALUES (?,?)"); $q->execute([$userId,$used?'consumed':'available']);
            $q = $pdo->prepare('SELECT * FROM commerce_trials WHERE user_id=? FOR UPDATE'); $q->execute([$userId]); $trial = $q->fetch();
            if ($used || $trial['status'] === 'consumed') throw new NishaCommerceException('Your free full mock is complete. Choose an access pass to start another mock.', 'mock_payment_required', 402);
            if ($trial['status'] === 'reserved' && ($trial['exam_id'] !== $examId || $trial['attempt_id'] !== $attemptId)) throw new NishaCommerceException('Your free mock is already in progress. Resume it before choosing another mock.', 'mock_trial_reserved', 402);
            $q = $pdo->prepare("UPDATE commerce_trials SET status='reserved',exam_id=?,attempt_id=?,reserved_at=COALESCE(reserved_at,UTC_TIMESTAMP()) WHERE user_id=?"); $q->execute([$examId,$attemptId,$userId]);
        }
        $q = $pdo->prepare('INSERT INTO mock_access_grants (user_id,attempt_id,exam_id,kind,expires_at) VALUES (?,?,?,?,?)');
        $q->execute([$userId,$attemptId,$examId,$paid?'paid':'trial',$paid?gmdate('Y-m-d H:i:s',time()+86400):null]);
        if ($paid) {
            $sourceOrder = nisha_commerce_active_order($pdo, $userId);
            if ($sourceOrder === null) throw new NishaCommerceException('Your access pass changed. Start this mock again.', 'mock_payment_changed', 409);
            nisha_commerce_fund_grant($pdo, $userId, $attemptId, $sourceOrder);
        }
        if ($owns) $pdo->commit(); return ['exam_id'=>$examId,'attempt_id'=>$attemptId,'kind'=>$paid?'paid':'trial'];
    } catch (Throwable $e) { if ($owns && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function nisha_commerce_assert_mock(PDO $pdo, int $userId, string $examId, string $attemptId): void
{
    nisha_commerce_valid_attempt($examId, $attemptId);
    $q = $pdo->prepare('SELECT exam_id,kind,finished_at,(expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) AS valid_now FROM mock_access_grants WHERE user_id=? AND attempt_id=?'); $q->execute([$userId,$attemptId]); $grant = $q->fetch();
    if ($grant && $grant['exam_id'] === $examId && ($grant['finished_at'] || ((bool)$grant['valid_now'] && ($grant['kind'] === 'trial' || nisha_commerce_grant_funding_valid($pdo, $userId, $attemptId))))) return;
    // Historical saved reports remain accessible and immutable after upgrade.
    $q = $pdo->prepare('SELECT 1 FROM exam_runs WHERE user_id=? AND attempt_id=? AND exam_id=? LIMIT 1'); $q->execute([$userId,$attemptId,$examId]);
    if ($q->fetchColumn()) return;
    throw new NishaCommerceException('Start this mock from the exam room before submitting. An access pass is required after your first free mock.', 'mock_access_required', 402);
}

function nisha_commerce_finish_mock(PDO $pdo, int $userId, string $examId, string $attemptId): void
{
    nisha_commerce_assert_mock($pdo,$userId,$examId,$attemptId);
    $owns = !$pdo->inTransaction(); if ($owns) $pdo->beginTransaction();
    try {
        nisha_commerce_lock_user($pdo,$userId);
        $q=$pdo->prepare('SELECT 1 FROM exam_runs WHERE user_id=? AND exam_id=? AND attempt_id=? LIMIT 1');$q->execute([$userId,$examId,$attemptId]);
        if (!$q->fetchColumn()) throw new NishaCommerceException('Save your completed mock before finishing the free trial.', 'mock_result_not_saved', 409);
        $q=$pdo->prepare('UPDATE mock_access_grants SET finished_at=COALESCE(finished_at,UTC_TIMESTAMP()) WHERE user_id=? AND attempt_id=? AND exam_id=?');$q->execute([$userId,$attemptId,$examId]);
        $q=$pdo->prepare("UPDATE commerce_trials SET status='consumed',completed_at=COALESCE(completed_at,UTC_TIMESTAMP()) WHERE user_id=? AND exam_id=? AND attempt_id=?");$q->execute([$userId,$examId,$attemptId]);
        if($owns)$pdo->commit();
    } catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
}

/** Fixed Stripe destination, verified TLS, bounded response; CLI injection only. */
function nisha_stripe_request(string $path, array $fields, string $idempotency, ?callable $transport=null): array
{
    if ($transport!==null && PHP_SAPI!=='cli') throw new LogicException('Test transports are CLI-only.');
    if ($path !== '/v1/checkout/sessions') throw new LogicException('Unsupported payment provider endpoint.');
    $settings=nisha_commerce_settings();
    if(!$settings['enabled']) throw new NishaCommerceException('Payments are not enabled yet. Your free practice remains available.', 'payments_not_configured');
    $body=http_build_query($fields,'','&',PHP_QUERY_RFC3986);
    $headers=['Authorization: Bearer '.$settings['secret_key'],'Content-Type: application/x-www-form-urlencoded','Idempotency-Key: '.$idempotency,'Stripe-Version: 2024-06-20'];
    if($transport!==null)$reply=$transport(['url'=>'https://api.stripe.com'.$path,'headers'=>$headers,'body'=>$body]);
    else {
        $raw='';$status=0;
        if(function_exists('curl_init')){
            $ch=curl_init('https://api.stripe.com'.$path);
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($h,string$chunk)use(&$raw):int{if(strlen($raw)+strlen($chunk)>1048576)return 0;$raw.=$chunk;return strlen($chunk);}]);
            $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            if($ok===false)throw new NishaCommerceException('Checkout could not connect. No access was changed; retry shortly.','checkout_connection');
        } elseif(extension_loaded('openssl')&&filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)){
            $context=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers),'content'=>$body,'timeout'=>30,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]]);
            $raw=@file_get_contents('https://api.stripe.com'.$path,false,$context,0,1048577);
            foreach($http_response_header??[]as$header)if(preg_match('/\AHTTP\/\S+ (\d{3})/',$header,$m))$status=(int)$m[1];
            if($raw===false)throw new NishaCommerceException('Checkout could not connect. No access was changed; retry shortly.','checkout_connection');
        } else throw new NishaCommerceException('The site owner needs to enable verified HTTPS requests for checkout.','checkout_transport');
        $reply=['status'=>$status,'body'=>$raw];
    }
    if(!is_int($reply['status']??null)||!is_string($reply['body']??null)||strlen($reply['body'])>1048576||$reply['status']<200||$reply['status']>=300)throw new NishaCommerceException('The payment provider could not create checkout. No access was granted.','checkout_provider');
    try{$decoded=json_decode($reply['body'],true,32,JSON_THROW_ON_ERROR);}catch(JsonException$e){throw new NishaCommerceException('Checkout returned an unreadable response. Please retry.','checkout_invalid');}
    if(!is_array($decoded))throw new NishaCommerceException('Checkout returned an invalid response. Please retry.','checkout_invalid');
    return$decoded;
}

function nisha_commerce_checkout(PDO $pdo, int $userId, ?callable $transport=null): array
{
    $settings=nisha_commerce_settings();
    if(!$settings['enabled'])throw new NishaCommerceException('Payments are not enabled yet. Free practice and your first full mock are available.','payments_not_configured');
    $pdo->beginTransaction();
    try {
        nisha_commerce_lock_user($pdo, $userId);
        if (nisha_commerce_has_paid($pdo, $userId)) throw new NishaCommerceException('Your pass is already active. You can purchase another after it expires.', 'pass_already_active', 409);
        $q=$pdo->prepare("SELECT * FROM payment_orders WHERE user_id=? AND status='pending' AND created_at>UTC_TIMESTAMP()-INTERVAL 24 HOUR ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
        $q->execute([$userId]);$order=$q->fetch();
        if(!$order){$id=bin2hex(random_bytes(16));$q=$pdo->prepare('INSERT INTO payment_orders (id,user_id,amount_minor,currency,access_days) VALUES (?,?,?,?,?)');$q->execute([$id,$userId,$settings['amount_minor'],$settings['currency'],$settings['access_days']]);$q=$pdo->prepare('SELECT * FROM payment_orders WHERE id=?');$q->execute([$id]);$order=$q->fetch();}
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
    if($order['checkout_id']&&$order['checkout_url'])return['order_id'=>$order['id'],'url'=>$order['checkout_url']];
    $fields=['mode'=>'payment','payment_method_types'=>['card'],'expires_at'=>strtotime($order['created_at'].' UTC')+86400,'line_items'=>[['price_data'=>['currency'=>$order['currency'],'unit_amount'=>(int)$order['amount_minor'],'product_data'=>['name'=>'NISHA full mock access — '.$order['access_days'].' days']],'quantity'=>1]],
        'client_reference_id'=>$order['id'],'metadata'=>['order_id'=>$order['id'],'user_id'=>(string)$userId],
        'payment_intent_data'=>['metadata'=>['order_id'=>$order['id'],'user_id'=>(string)$userId]],
        'success_url'=>$settings['site_url'].'/pricing.php?checkout=success','cancel_url'=>$settings['site_url'].'/pricing.php?checkout=cancelled'];
    $session=nisha_stripe_request('/v1/checkout/sessions',$fields,'nisha-checkout-'.$order['id'],$transport);
    $url=$session['url']??'';$parts=is_string($url)?parse_url($url):false;
    if(!is_string($session['id']??null)||!preg_match('/\Acs_(?:test_|live_)?[A-Za-z0-9_]+\z/D',$session['id'])||!is_array($parts)||($parts['scheme']??'')!=='https'||($parts['host']??'')!=='checkout.stripe.com'||isset($parts['user'])||isset($parts['pass'])||(isset($parts['port'])&&$parts['port']!==443)||preg_match('/[\r\n\x00]/',$url))throw new NishaCommerceException('Checkout could not be safely opened. No access was granted.','checkout_invalid');
    $q=$pdo->prepare('UPDATE payment_orders SET checkout_id=?,checkout_url=? WHERE id=? AND user_id=? AND checkout_id IS NULL');$q->execute([$session['id'],$url,$order['id'],$userId]);
    return['order_id'=>$order['id'],'url'=>$url];
}

function nisha_stripe_verify_event(string $raw, string $signature, string $secret, ?int $now=null): array
{
    if(strlen($raw)>1048576||strlen($signature)>4096||$secret==='')throw new NishaCommerceException('Invalid payment signature.','webhook_signature',400);
    $timestamp=null;$signatures=[];
    foreach(explode(',',$signature)as$part){$pair=explode('=',trim($part),2);if(count($pair)!==2)continue;if($pair[0]==='t'&&ctype_digit($pair[1]))$timestamp=(int)$pair[1];if($pair[0]==='v1'&&preg_match('/\A[a-f0-9]{64}\z/D',$pair[1]))$signatures[]=$pair[1];}
    if($timestamp===null||abs(($now??time())-$timestamp)>300)throw new NishaCommerceException('Invalid or expired payment signature.','webhook_signature',400);
    $expected=hash_hmac('sha256',$timestamp.'.'.$raw,$secret);$valid=false;foreach($signatures as$value)if(hash_equals($expected,$value))$valid=true;
    if(!$valid)throw new NishaCommerceException('Invalid payment signature.','webhook_signature',400);
    try{$event=json_decode($raw,true,40,JSON_THROW_ON_ERROR);}catch(JsonException$e){throw new NishaCommerceException('Invalid payment event.','webhook_event',400);}
    if(!is_array($event)||!is_string($event['id']??null)||!preg_match('/\Aevt_[A-Za-z0-9_]{1,240}\z/D',$event['id'])||!is_string($event['type']??null)||strlen($event['type'])>100||!is_array($event['data']['object']??null))throw new NishaCommerceException('Invalid payment event.','webhook_event',400);
    return$event;
}

/** Only signed, bound, settled Stripe events can grant or revoke access. */
function nisha_commerce_apply_event(PDO $pdo, array $event): array
{
    $settings=nisha_commerce_settings();
    if(!$settings['provider_ready'])throw new NishaCommerceException('Payment processing is not configured.','payments_not_configured');
    if(!is_bool($event['livemode']??null)||$event['livemode']!==$settings['live'])throw new NishaCommerceException('The payment event is for a different mode.','webhook_mode',400);
    $object=$event['data']['object'];$type=$event['type'];
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT 1 FROM payment_events WHERE event_id=?');$q->execute([$event['id']]);if($q->fetchColumn()){$pdo->commit();return['received'=>true,'duplicate'=>true];}
        if(in_array($type,['checkout.session.completed','checkout.session.async_payment_succeeded'],true)){
            $id=$object['metadata']['order_id']??null;
            if(!is_string($id)||!preg_match('/\A[a-f0-9]{32}\z/D',$id))throw new NishaCommerceException('Payment order binding is invalid.','webhook_binding',400);
            $q=$pdo->prepare('SELECT * FROM payment_orders WHERE id=? FOR UPDATE');$q->execute([$id]);$order=$q->fetch();
            if(!$order)throw new NishaCommerceException('Payment order was not found.','webhook_order',400);
            if(($object['mode']??null)!=='payment'||($object['status']??null)!=='complete'||($object['payment_status']??null)!=='paid'||($object['id']??null)!==$order['checkout_id']||($object['client_reference_id']??null)!==$order['id']
                ||($object['metadata']['user_id']??null)!==(string)$order['user_id']||!is_int($object['amount_total']??null)||$object['amount_total']!==(int)$order['amount_minor']||($object['currency']??null)!==$order['currency']||!is_string($object['payment_intent']??null)||!preg_match('/\Api_[A-Za-z0-9_]{1,240}\z/D',$object['payment_intent']))throw new NishaCommerceException('Payment details do not match this order.','webhook_binding',400);
            if($order['status']==='pending'){
                nisha_commerce_lock_user($pdo,(int)$order['user_id']);
                // Each purchase grants only its own advertised duration. It
                // cannot inherit time from another purchase later refunded.
                $start=gmdate('Y-m-d H:i:s');$end=gmdate('Y-m-d H:i:s',time()+(int)$order['access_days']*86400);
                $q=$pdo->prepare("UPDATE payment_orders SET status='paid',payment_intent=?,paid_at=UTC_TIMESTAMP() WHERE id=?");$q->execute([$object['payment_intent'],$id]);
                $q=$pdo->prepare('INSERT INTO student_entitlements (user_id,order_id,starts_at,expires_at) VALUES (?,?,?,?)');$q->execute([$order['user_id'],$id,$start,$end]);
            } elseif($order['payment_intent']!==$object['payment_intent'])throw new NishaCommerceException('Payment reference conflict.','webhook_binding',400);
        } elseif(in_array($type,['charge.refunded','charge.dispute.created','charge.dispute.funds_withdrawn'],true)){
            $intent=$object['payment_intent']??null;
            if(!is_string($intent)||!preg_match('/\Api_[A-Za-z0-9_]{1,240}\z/D',$intent))throw new NishaCommerceException('Refund payment reference is invalid.','webhook_binding',400);
            $q=$pdo->prepare('SELECT * FROM payment_orders WHERE payment_intent=? FOR UPDATE');$q->execute([$intent]);$order=$q->fetch();
            if(!$order)throw new NishaCommerceException('The payment is not ready for a refund update.','webhook_order',409);
            if($type==='charge.refunded'){
                if(($object['currency']??null)!==$order['currency']||!is_int($object['amount_refunded']??null)||$object['amount_refunded']<0||$object['amount_refunded']>(int)$order['amount_minor'])throw new NishaCommerceException('Refund details do not match the order.','webhook_binding',400);
                $refunded=max((int)$order['refunded_minor'],$object['amount_refunded']);
                foreach($object['refunds']['data']??[]as$refund){if(($refund['status']??'')!=='succeeded')continue;if(!is_string($refund['id']??null)||!preg_match('/\Are_[A-Za-z0-9_]{1,240}\z/D',$refund['id'])||!is_int($refund['amount']??null)||$refund['amount']<1||($refund['currency']??null)!==$order['currency'])throw new NishaCommerceException('Invalid refund event.','webhook_binding',400);$q=$pdo->prepare('INSERT IGNORE INTO commerce_refunds (refund_id,order_id,amount_minor,currency,status) VALUES (?,?,?,?,?)');$q->execute([$refund['id'],$order['id'],$refund['amount'],$order['currency'],'succeeded']);}
                $q=$pdo->prepare('UPDATE payment_orders SET refunded_minor=?,status=? WHERE id=?');$q->execute([$refunded,$refunded>=(int)$order['amount_minor']?'refunded':'partially_refunded',$order['id']]);
            } else {$q=$pdo->prepare("UPDATE payment_orders SET status='disputed' WHERE id=?");$q->execute([$order['id']]);}
            // Any settled refund/dispute revokes this pass; other purchased passes survive.
            $q=$pdo->prepare('UPDATE student_entitlements SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()) WHERE order_id=?');$q->execute([$order['id']]);
        }
        $q=$pdo->prepare('INSERT INTO payment_events (event_id,event_type) VALUES (?,?)');$q->execute([$event['id'],$type]);$pdo->commit();return['received'=>true,'duplicate'=>false];
    } catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
