<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-accounts.php';

const NISHA_MOBILE_PROTOCOL_VERSION = 1;
const NISHA_MOBILE_TOKEN_LIFETIME = 2592000;

class NishaMobileException extends RuntimeException
{
    public function __construct(public string $publicMessage, public string $errorCode = 'mobile_unavailable', public int $httpStatus = 503)
    { parent::__construct($publicMessage); }
}

/** Exact app origins. Development opt-ins may only name loopback origins. */
function nisha_mobile_origins(): array
{
    $origins = ['https://localhost', 'capacitor://localhost', 'https://nishaglobal.com', 'https://www.nishaglobal.com'];
    $extra = getenv('NISHA_MOBILE_DEVELOPMENT_ORIGINS');
    $extra = $extra !== false && trim($extra) !== '' ? explode(',', $extra) : config('mobile_development_origins', []);
    if (is_string($extra)) $extra = explode(',', $extra);
    if (is_array($extra)) foreach ($extra as $value) {
        if (!is_string($value)) continue;
        $value = trim($value);
        if (preg_match('~\Ahttps?://(?:localhost|127\.0\.0\.1|\[::1\])(?::[1-9][0-9]{0,4})?\z~D', $value)) {
            $parts = parse_url($value);
            if (is_array($parts) && ($parts['port'] ?? 1) <= 65535) $origins[] = $value;
        }
    }
    return array_values(array_unique($origins));
}

function nisha_mobile_origin_allowed(?string $origin): bool
{ return $origin === null || $origin === '' || in_array($origin, nisha_mobile_origins(), true); }

function nisha_mobile_transport_allowed(array $server): bool
{
    if ((!empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off') || (string)($server['SERVER_PORT'] ?? '') === '443') return true;
    return in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)
        && preg_match('~\A(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]+)?\z~D', (string)($server['HTTP_HOST'] ?? '')) === 1;
}

function nisha_mobile_ready(PDO $pdo): void
{
    try {
        $pdo->query('SELECT id,user_id,token_hash,auth_version,device_name,expires_at,revoked_at FROM mobile_access_tokens LIMIT 0');
        $pdo->query('SELECT scope_hash,window_bucket,used_count FROM mobile_auth_limits LIMIT 0');
        $pdo->query('SELECT user_id,status,reason,requested_at FROM mobile_deletion_requests LIMIT 0');
        $pdo->query('SELECT user_id,status,auth_version FROM account_security LIMIT 0');
    } catch (Throwable $exception) { throw new NishaMobileException('The mobile service needs the latest database update. Offline practice remains available.', 'mobile_setup_required'); }
}

function nisha_mobile_user(array $row): array
{
    return ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'email'=>(string)$row['email'], 'created_at'=>(string)$row['created_at']];
}

function nisha_mobile_body_keys(array $input, array $required, array $optional = []): void
{
    if (array_diff($required, array_keys($input)) || array_diff(array_keys($input), array_merge($required, $optional))) {
        throw new NishaMobileException('Send only the required fields for this request.', 'mobile_request_invalid', 422);
    }
}

function nisha_mobile_email(mixed $email): string
{
    if (!is_string($email)) throw new NishaMobileException('Enter a valid email address.', 'mobile_email_invalid', 422);
    $email = strtolower(trim($email));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new NishaMobileException('Enter a valid email address.', 'mobile_email_invalid', 422);
    return $email;
}

function nisha_mobile_password(mixed $password, bool $creating = false): string
{
    if (!is_string($password) || !preg_match('//u', $password) || str_contains($password, "\0") || strlen($password) > 72 || $password === '') throw new NishaMobileException('Enter a valid password of at most 72 bytes.', 'mobile_password_invalid', 422);
    $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
    if ($creating && $length < 8) throw new NishaMobileException('Choose a password with at least 8 characters.', 'mobile_password_invalid', 422);
    return $password;
}

function nisha_mobile_text(mixed $value, int $min, int $max, string $label): string
{
    if (!is_string($value) || !preg_match('//u', $value) || preg_match('/[\x00-\x1F\x7F]/', $value)) throw new NishaMobileException('Enter a valid ' . $label . '.', 'mobile_request_invalid', 422);
    $value = trim($value); $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length < $min || $length > $max) throw new NishaMobileException('Use ' . $min . '–' . $max . ' characters for the ' . $label . '.', 'mobile_request_invalid', 422);
    return $value;
}

/** Persisted, fixed 15-minute windows; sorted locks avoid cross-key inversions. */
function nisha_mobile_rate_limit(PDO $pdo, array $limits, ?int $now = null): void
{
    if ($pdo->inTransaction()) throw new LogicException('Rate limiting needs its own transaction.');
    $bucket = intdiv($now ?? time(), 900); $scopes = [];
    foreach ($limits as $scope=>$limit) $scopes[hash('sha256', $scope)] = (int)$limit;
    ksort($scopes); $pdo->beginTransaction();
    try {
        foreach ($scopes as $hash=>$limit) {
            $q=$pdo->prepare('INSERT IGNORE INTO mobile_auth_limits(scope_hash,window_bucket) VALUES(?,?)'); $q->execute([$hash,$bucket]);
            $q=$pdo->prepare('SELECT used_count FROM mobile_auth_limits WHERE scope_hash=? AND window_bucket=? FOR UPDATE'); $q->execute([$hash,$bucket]);
            if ((int)$q->fetchColumn() >= $limit) throw new NishaMobileException('Too many attempts. Wait up to 15 minutes before trying again.', 'mobile_rate_limited', 429);
        }
        foreach ($scopes as $hash=>$limit) { $q=$pdo->prepare('UPDATE mobile_auth_limits SET used_count=used_count+1 WHERE scope_hash=? AND window_bucket=?'); $q->execute([$hash,$bucket]); }
        $pdo->commit();
    } catch (Throwable $exception) { if($pdo->inTransaction())$pdo->rollBack(); throw $exception; }
}

/** Caller owns user-row lock and transaction. Tokens never enter browser sessions. */
function nisha_mobile_issue_token(PDO $pdo, array $user, string $deviceName): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Token issuance requires a transaction.');
    $security = nisha_admin_accounts_security_lock($pdo, (int)$user['id']);
    if ($security['status'] !== 'active') throw new NishaMobileException('The email or password is incorrect, or the account is unavailable.', 'mobile_credentials_invalid', 401);
    $token='nm1_'.bin2hex(random_bytes(32)); $expires=gmdate('Y-m-d H:i:s',time()+NISHA_MOBILE_TOKEN_LIFETIME);
    $q=$pdo->prepare('INSERT INTO mobile_access_tokens(user_id,token_hash,auth_version,device_name,expires_at) VALUES(?,?,?,?,?)');
    $q->execute([(int)$user['id'],hash('sha256',$token),(int)$security['auth_version'],$deviceName,$expires]);
    // Bound remembered devices. The newest ten may remain active.
    $q=$pdo->prepare('SELECT id FROM mobile_access_tokens WHERE user_id=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 10,1000'); $q->execute([$user['id']]);
    foreach($q->fetchAll() as $old){$r=$pdo->prepare('UPDATE mobile_access_tokens SET revoked_at=UTC_TIMESTAMP() WHERE id=?');$r->execute([$old['id']]);}
    return ['success'=>true,'access_token'=>$token,'token_type'=>'Bearer','expires_at'=>$expires,'user'=>nisha_mobile_user($user)];
}

function nisha_mobile_login(PDO $pdo, array $input, string $ip): array
{
    nisha_mobile_body_keys($input,['email','password'],['device_name']); nisha_mobile_ready($pdo);
    $email=nisha_mobile_email($input['email']); $password=nisha_mobile_password($input['password']);
    $device=nisha_mobile_text($input['device_name']??'NISHA app',1,80,'device name');
    nisha_mobile_rate_limit($pdo,['login-email:'.$email=>5,'login-ip:'.$ip=>40]);
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT id,name,email,password_hash,created_at FROM users WHERE email=? FOR UPDATE'); $q->execute([$email]); $user=$q->fetch();
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid=password_verify($password,$user['password_hash']??$dummy);
        if(!$user||!$valid)throw new NishaMobileException('The email or password is incorrect, or the account is unavailable.','mobile_credentials_invalid',401);
        if(password_needs_rehash($user['password_hash'],PASSWORD_DEFAULT)){$q=$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');$q->execute([password_hash($password,PASSWORD_DEFAULT),$user['id']]);}
        $reply=nisha_mobile_issue_token($pdo,$user,$device);$pdo->commit();return$reply;
    } catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function nisha_mobile_register(PDO $pdo, array $input, string $ip): array
{
    nisha_mobile_body_keys($input,['name','email','password','password_confirmation'],['device_name']); nisha_mobile_ready($pdo);
    $name=nisha_mobile_text($input['name'],2,100,'name');$email=nisha_mobile_email($input['email']);$password=nisha_mobile_password($input['password'],true);
    if(!is_string($input['password_confirmation'])||!hash_equals($password,$input['password_confirmation']))throw new NishaMobileException('The two passwords do not match.','mobile_password_invalid',422);
    $device=nisha_mobile_text($input['device_name']??'NISHA app',1,80,'device name');
    nisha_mobile_rate_limit($pdo,['register-email:'.$email=>5,'register-ip:'.$ip=>10]);
    $pdo->beginTransaction();
    try{
        $q=$pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
        $id=(int)$pdo->lastInsertId();$q=$pdo->prepare('SELECT id,name,email,created_at FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$user=$q->fetch();
        $reply=nisha_mobile_issue_token($pdo,$user,$device);$pdo->commit();return$reply;
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&(int)($e->errorInfo[1]??0)===1062)throw new NishaMobileException('An account with this email already exists. Sign in using your own password.','mobile_email_exists',409);throw$e;}
}

function nisha_mobile_bearer(array $server): ?string
{
    $header=$server['HTTP_AUTHORIZATION']??$server['REDIRECT_HTTP_AUTHORIZATION']??null;
    if($header===null||$header==='')return null;
    if(!is_string($header)||!preg_match('/\ABearer (nm1_[a-f0-9]{64})\z/iD',$header,$match))throw new NishaMobileException('Sign in again to use your NISHA account.','mobile_token_invalid',401);
    return$match[1];
}

function nisha_mobile_authenticate(PDO $pdo, string $token): array
{
    nisha_mobile_ready($pdo);
    if(!preg_match('/\Anm1_[a-f0-9]{64}\z/D',$token))throw new NishaMobileException('Sign in again to use your NISHA account.','mobile_token_invalid',401);
    $q=$pdo->prepare('SELECT t.id AS token_id,t.auth_version,t.expires_at,t.revoked_at,u.id,u.name,u.email,u.created_at FROM mobile_access_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>UTC_TIMESTAMP()');$q->execute([hash('sha256',$token)]);$row=$q->fetch();
    if(!$row||!nisha_account_session_valid($pdo,(int)$row['id'],(int)$row['auth_version']))throw new NishaMobileException('Your app session expired or account access changed. Sign in again.','mobile_token_invalid',401);
    $q=$pdo->prepare('UPDATE mobile_access_tokens SET last_used_at=UTC_TIMESTAMP() WHERE id=? AND (last_used_at IS NULL OR last_used_at<UTC_TIMESTAMP()-INTERVAL 5 MINUTE)');$q->execute([$row['token_id']]);
    return nisha_mobile_user($row);
}

function nisha_mobile_logout(PDO $pdo, string $token): array
{
    $q=$pdo->prepare('UPDATE mobile_access_tokens SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=? AND revoked_at IS NULL');$q->execute([hash('sha256',$token)]);
    return['success'=>true,'message'=>'Signed out on this device.'];
}

function nisha_mobile_deletion_request(PDO $pdo, array $user, array $input, string $ip): array
{
    nisha_mobile_body_keys($input,['password','confirmation'],['reason']);
    if($input['confirmation']!==true)throw new NishaMobileException('Confirm that you want to request account deletion.','mobile_confirmation_required',422);
    $password=nisha_mobile_password($input['password']);$reason=nisha_mobile_text($input['reason']??'',0,500,'reason');
    nisha_mobile_rate_limit($pdo,['deletion-user:'.$user['id']=>5,'deletion-ip:'.$ip=>40]);
    $pdo->beginTransaction();
    try{
        $q=$pdo->prepare('SELECT id,password_hash FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);$row=$q->fetch();
        if(!$row||!password_verify($password,$row['password_hash']))throw new NishaMobileException('Your current password is incorrect.','mobile_credentials_invalid',401);
        $security=nisha_admin_accounts_security_lock($pdo,(int)$user['id']);
        if($security['status']!=='active')throw new NishaMobileException('This account is unavailable.','mobile_token_invalid',401);
        $q=$pdo->prepare("INSERT INTO mobile_deletion_requests(user_id,reason) VALUES(?,?) ON DUPLICATE KEY UPDATE reason=IF(status='pending',VALUES(reason),reason)");$q->execute([$user['id'],$reason]);
        $q=$pdo->prepare('UPDATE account_security SET auth_version=auth_version+1 WHERE user_id=?');$q->execute([$user['id']]);
        $q=$pdo->prepare('UPDATE mobile_access_tokens SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL');$q->execute([$user['id']]);
        $q=$pdo->prepare('SELECT status,requested_at FROM mobile_deletion_requests WHERE user_id=?');$q->execute([$user['id']]);$request=$q->fetch();
        $pdo->commit();
        return['success'=>true,'status'=>$request['status'],'requested_at'=>$request['requested_at'],'message'=>'Your account deletion request was received. You are signed out on all devices. The owner will process personal-data removal; records required for financial or legal retention may be retained. This confirmation is a request receipt, not completed deletion.'];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
