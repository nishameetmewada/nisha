<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/** Optional additive security state. Only an absent table permits legacy login. */
function nisha_account_security(PDO $pdo, int $userId): array
{
    try {
        $q = $pdo->prepare('SELECT status,auth_version FROM account_security WHERE user_id=?');
        $q->execute([$userId]); $row = $q->fetch();
        return ['status' => $row['status'] ?? 'active', 'auth_version' => (int) ($row['auth_version'] ?? 0), 'available' => true];
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '42S02') return ['status' => 'active', 'auth_version' => 0, 'available' => false];
        throw $exception;
    }
}

function nisha_account_login_version(PDO $pdo, int $userId): ?int
{
    $security = nisha_account_security($pdo, $userId);
    return $security['status'] === 'active' ? $security['auth_version'] : null;
}

function nisha_account_session_valid(PDO $pdo, int $userId, mixed $sessionVersion): bool
{
    $security = nisha_account_security($pdo, $userId);
    return $security['status'] === 'active' && is_int($sessionVersion) && $sessionVersion >= 0 && $security['auth_version'] === $sessionVersion;
}

function nisha_admin_accounts_configured_ids(): array
{
    $ids = config('admin_user_ids', []);
    if (is_string($ids)) $ids = explode(',', $ids);
    if (!is_array($ids)) return [];
    return array_values(array_unique(array_filter(array_map(static fn ($id): int => is_scalar($id) && preg_match('/\A[1-9][0-9]*\z/D', trim((string) $id)) ? (int) $id : 0, $ids), static fn (int $id): bool => $id > 0)));
}

function nisha_admin_accounts_ready(PDO $pdo): void
{
    try {
        $pdo->query('SELECT user_id,status,auth_version,reauth_failures,reauth_blocked_until FROM account_security LIMIT 0');
        $pdo->query('SELECT id FROM admin_account_locks LIMIT 0');
        $pdo->query('SELECT id,actor_id,target_id,action,reason,before_json,after_json FROM admin_account_audit LIMIT 0');
        $pdo->query('SELECT user_id,role FROM user_roles LIMIT 0');
    } catch (Throwable $exception) { throw new RuntimeException('Account management needs the updated database schema. Existing records have not been changed.', 0, $exception); }
}

function nisha_admin_accounts_is_admin(PDO $pdo, int $userId): bool
{
    if (in_array($userId, nisha_admin_accounts_configured_ids(), true)) return true;
    $q = $pdo->prepare("SELECT 1 FROM user_roles WHERE user_id=? AND role='admin'");
    $q->execute([$userId]); return (bool) $q->fetchColumn();
}

function nisha_admin_accounts_row(PDO $pdo, int $userId): ?array
{
    $q = $pdo->prepare('SELECT u.id,u.name,u.email,u.created_at,COALESCE(s.status,\'active\') AS status,COALESCE(s.auth_version,0) AS auth_version,r.role FROM users u LEFT JOIN account_security s ON s.user_id=u.id LEFT JOIN user_roles r ON r.user_id=u.id WHERE u.id=?');
    $q->execute([$userId]); $row = $q->fetch();
    if (!$row) return null;
    $row['id'] = (int) $row['id']; $row['auth_version'] = (int) $row['auth_version'];
    $row['is_config_admin'] = in_array($userId, nisha_admin_accounts_configured_ids(), true);
    $row['is_admin'] = $row['is_config_admin'] || $row['role'] === 'admin';
    $row['teacher_status'] = null;
    try { $q = $pdo->prepare('SELECT status FROM teacher_profiles WHERE user_id=?'); $q->execute([$userId]); $row['teacher_status'] = $q->fetchColumn() ?: null; }
    catch (PDOException $exception) { if ((string) $exception->getCode() !== '42S02') throw $exception; }
    return $row;
}

function nisha_admin_accounts_search(PDO $pdo, string $query = '', int $page = 1, int $perPage = 25): array
{
    nisha_admin_accounts_ready($pdo);
    $query = trim($query); if (strlen($query) > 254 || !preg_match('//u', $query)) throw new InvalidArgumentException('Use a shorter account search.');
    $perPage = max(1, min(50, $perPage));
    $where = ''; $values = [];
    if ($query !== '') { $where = " WHERE name LIKE ? ESCAPE '=' OR email LIKE ? ESCAPE '=' OR CAST(id AS CHAR)=?"; $like = '%' . strtr($query, ['='=>'==','%'=>'=%','_'=>'=_']) . '%'; $values = [$like,$like,$query]; }
    $q = $pdo->prepare('SELECT COUNT(*) FROM users' . $where); $q->execute($values); $total = (int) $q->fetchColumn();
    $pages = (int) ceil($total / $perPage); $page = max(1, min(max(1, $pages), $page));
    $q = $pdo->prepare('SELECT id FROM users' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page-1)*$perPage)); $q->execute($values);
    $items = []; foreach ($q->fetchAll() as $row) $items[] = nisha_admin_accounts_row($pdo, (int) $row['id']);
    return ['items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
}

function nisha_admin_accounts_detail(PDO $pdo, int $userId): ?array
{
    nisha_admin_accounts_ready($pdo); $row = nisha_admin_accounts_row($pdo, $userId);
    if ($row === null) return null;
    $q = $pdo->prepare('SELECT id,actor_id,target_id,action,reason,before_json,after_json,created_at FROM admin_account_audit WHERE target_id=? ORDER BY id DESC LIMIT 50'); $q->execute([$userId]);
    $row['audit'] = $q->fetchAll(); return $row;
}

function nisha_admin_accounts_text(mixed $value, string $label, int $min, int $max): string
{
    if (!is_string($value) || !preg_match('//u', $value) || preg_match('/[\x00-\x1F\x7F]/', $value)) throw new InvalidArgumentException('Enter a valid ' . $label . '.');
    $value = trim($value); $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
    if ($length < $min || $length > $max) throw new InvalidArgumentException('Enter a ' . $label . ' using ' . $min . '–' . $max . ' characters.');
    return $value;
}

function nisha_admin_accounts_password(mixed $password, mixed $confirmation): string
{
    if (!is_string($password) || !preg_match('//u', $password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) throw new InvalidArgumentException('Choose a new password with 12–72 bytes and no null characters.');
    if (!is_string($confirmation) || !hash_equals($password, $confirmation)) throw new InvalidArgumentException('The new passwords do not match.');
    return $password;
}

function nisha_admin_accounts_lock(PDO $pdo): void
{
    $pdo->exec('INSERT IGNORE INTO admin_account_locks(id) VALUES(1)');
    $pdo->query('SELECT id FROM admin_account_locks WHERE id=1 FOR UPDATE')->fetchColumn();
}

function nisha_admin_accounts_security_lock(PDO $pdo, int $userId): array
{
    $q = $pdo->prepare('INSERT IGNORE INTO account_security(user_id) VALUES(?)'); $q->execute([$userId]);
    $q = $pdo->prepare('SELECT *,COALESCE(reauth_blocked_until>UTC_TIMESTAMP(),0) AS blocked FROM account_security WHERE user_id=? FOR UPDATE'); $q->execute([$userId]);
    return $q->fetch();
}

function nisha_admin_accounts_snapshot(array $row, bool $passwordChanged = false): array
{
    $safe = array_intersect_key($row, array_flip(['id','name','email','status','auth_version','is_admin','is_config_admin','role','teacher_status']));
    if ($passwordChanged) $safe['password_changed'] = true;
    return $safe;
}

function nisha_admin_accounts_audit(PDO $pdo, ?int $actorId, int $targetId, string $action, string $reason, array $before, array $after): void
{
    $q = $pdo->prepare('INSERT INTO admin_account_audit(actor_id,target_id,action,reason,before_json,after_json) VALUES(?,?,?,?,?,?)');
    $q->execute([$actorId,$targetId,$action,$reason,json_encode($before, JSON_THROW_ON_ERROR),json_encode($after, JSON_THROW_ON_ERROR)]);
}

function nisha_admin_accounts_active_admin_count(PDO $pdo, int $exceptId = 0): int
{
    $ids = nisha_admin_accounts_configured_ids();
    $condition = "r.role='admin'" . ($ids ? ' OR u.id IN (' . implode(',', $ids) . ')' : '');
    $q = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN user_roles r ON r.user_id=u.id LEFT JOIN account_security s ON s.user_id=u.id WHERE u.id<>? AND COALESCE(s.status,'active')='active' AND (" . $condition . ')');
    $q->execute([$exceptId]); return (int) $q->fetchColumn();
}

/** Caller holds the account mutex. Failed attempts commit only their throttle. */
function nisha_admin_accounts_reauthenticate_locked(PDO $pdo, int $actorId, string $password): void
{
    $q = $pdo->prepare('SELECT id,password_hash FROM users WHERE id=? FOR UPDATE'); $q->execute([$actorId]); $actor = $q->fetch();
    if (!$actor || !nisha_admin_accounts_is_admin($pdo, $actorId)) throw new InvalidArgumentException('Administrator access is required.');
    $security = nisha_admin_accounts_security_lock($pdo, $actorId);
    if ($security['status'] !== 'active') throw new InvalidArgumentException('Administrator access is required.');
    if ((bool) $security['blocked']) throw new InvalidArgumentException('Please wait a minute before confirming your administrator password again.');
    if (!password_verify($password, (string) $actor['password_hash'])) {
        $failures = (int) $security['reauth_failures'] + 1;
        $q = $pdo->prepare('UPDATE account_security SET reauth_failures=?,reauth_blocked_until=IF(?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 60 SECOND),NULL) WHERE user_id=?');
        $q->execute([$failures >= 5 ? 0 : $failures,$failures >= 5 ? 1 : 0,$actorId]);
        $pdo->commit(); throw new InvalidArgumentException('Your current administrator password is incorrect.');
    }
    $q = $pdo->prepare('UPDATE account_security SET reauth_failures=0,reauth_blocked_until=NULL WHERE user_id=?'); $q->execute([$actorId]);
}

/** Confirm an owner password for another owner-only settings page. */
function nisha_admin_accounts_reauthenticate(PDO $pdo, int $actorId, mixed $password): void
{
    nisha_admin_accounts_ready($pdo);
    if (!is_string($password) || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) throw new InvalidArgumentException('Enter your current administrator password.');
    if ($pdo->inTransaction()) throw new LogicException('Password confirmation requires a separate transaction.');
    $pdo->beginTransaction();
    try { nisha_admin_accounts_lock($pdo); nisha_admin_accounts_reauthenticate_locked($pdo, $actorId, $password); $pdo->commit(); }
    catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}

/** All security decisions use freshly locked rows, never the submitted DTO. */
function nisha_admin_accounts_change(PDO $pdo, int $actorId, int $targetId, string $action, array $input): array
{
    nisha_admin_accounts_ready($pdo);
    if ($actorId < 1 || $targetId < 1 || !in_array($action, ['edit_profile','reset_password','suspend','restore','grant_admin','revoke_admin'], true)) throw new InvalidArgumentException('Choose a valid account action.');
    $reason = nisha_admin_accounts_text($input['reason'] ?? null, 'reason', 5, 500);
    $password = $input['current_password'] ?? null;
    if (!is_string($password) || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) throw new InvalidArgumentException('Enter your current administrator password.');
    $name = null; $email = null; $newPassword = null;
    if ($action === 'edit_profile') {
        $name = nisha_admin_accounts_text($input['name'] ?? null, 'name', 2, 100);
        $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) throw new InvalidArgumentException('Enter a valid email address.');
    }
    if ($action === 'reset_password') $newPassword = nisha_admin_accounts_password($input['new_password'] ?? null, $input['password_confirmation'] ?? null);
    if ($pdo->inTransaction()) throw new LogicException('Account changes require a separate transaction.');
    $pdo->beginTransaction();
    try {
        nisha_admin_accounts_lock($pdo);
        nisha_admin_accounts_reauthenticate_locked($pdo, $actorId, $password);
        $q = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $q->execute([$targetId]);
        if (!$q->fetchColumn()) throw new InvalidArgumentException('This account no longer exists.');
        nisha_admin_accounts_security_lock($pdo, $targetId);
        $before = nisha_admin_accounts_row($pdo, $targetId);
        if (($action === 'suspend' || $action === 'revoke_admin') && $targetId === $actorId) throw new InvalidArgumentException('You cannot suspend or remove administrator access from your own account.');
        if ($action === 'revoke_admin' && $before['is_config_admin']) throw new InvalidArgumentException('This administrator is configured privately. Remove its numeric ID from the private configuration before changing the role.');
        if (in_array($action, ['suspend','revoke_admin'], true) && $before['is_admin'] && $before['status'] === 'active') {
            if (nisha_admin_accounts_active_admin_count($pdo, $targetId) < 1) throw new InvalidArgumentException('Keep at least one active administrator account.');
        }
        $invalidate = false;
        if ($action === 'edit_profile') {
            $q = $pdo->prepare('UPDATE users SET name=?,email=? WHERE id=?'); $q->execute([$name,$email,$targetId]); $invalidate = $email !== $before['email'];
        } elseif ($action === 'reset_password') {
            $q = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?'); $q->execute([password_hash($newPassword, PASSWORD_DEFAULT),$targetId]); $invalidate = true;
        } elseif ($action === 'suspend' || $action === 'restore') {
            $status = $action === 'suspend' ? 'suspended' : 'active';
            if ($before['status'] === $status) throw new InvalidArgumentException('This account already has that status.');
            $q = $pdo->prepare('UPDATE account_security SET status=? WHERE user_id=?'); $q->execute([$status,$targetId]); $invalidate = true;
        } elseif ($action === 'grant_admin') {
            if ($before['is_admin']) throw new InvalidArgumentException('This account already has administrator access.');
            if ($before['status'] !== 'active') throw new InvalidArgumentException('Restore the account before granting administrator access.');
            $q = $pdo->prepare("INSERT INTO user_roles(user_id,role,assigned_by) VALUES(?,'admin',?) ON DUPLICATE KEY UPDATE role='admin',assigned_by=VALUES(assigned_by)"); $q->execute([$targetId,$actorId]); $invalidate = true;
        } else {
            if (!$before['is_admin']) throw new InvalidArgumentException('This account does not have administrator access.');
            $q = $pdo->prepare("DELETE FROM user_roles WHERE user_id=? AND role='admin'"); $q->execute([$targetId]); $invalidate = true;
        }
        if ($invalidate) { $q = $pdo->prepare('UPDATE account_security SET auth_version=auth_version+1 WHERE user_id=?'); $q->execute([$targetId]); }
        $after = nisha_admin_accounts_row($pdo, $targetId);
        nisha_admin_accounts_audit($pdo, $actorId, $targetId, $action, $reason, nisha_admin_accounts_snapshot($before), nisha_admin_accounts_snapshot($after, $action === 'reset_password'));
        $pdo->commit(); return nisha_admin_accounts_detail($pdo, $targetId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') throw new InvalidArgumentException('That email is already in use, or the account changed. Refresh and try again.', 0, $exception);
        throw $exception;
    }
}

/** Private CLI only: no email-triggered promotion and no default password. */
function nisha_admin_accounts_bootstrap(PDO $pdo, string $mode, array $input): int
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Private command-line access is required.');
    nisha_admin_accounts_ready($pdo);
    if (!in_array($mode, ['create','promote','revoke'], true)) throw new InvalidArgumentException('Choose create, promote or revoke.');
    if ($pdo->inTransaction()) throw new LogicException('Administrator setup requires a separate transaction.');
    $name = $email = $password = null; $userId = 0;
    if ($mode === 'create') {
        $name = nisha_admin_accounts_text($input['name'] ?? null, 'name', 2, 100);
        $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) throw new InvalidArgumentException('Enter a valid email address.');
        $password = nisha_admin_accounts_password($input['password'] ?? null, $input['password_confirmation'] ?? null);
    } else {
        $rawId = $input['user_id'] ?? null;
        if ((!is_int($rawId) && (!is_string($rawId) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $rawId))) || (int) $rawId < 1) throw new InvalidArgumentException('Choose an existing numeric account ID.');
        $userId = (int) $rawId;
    }
    $pdo->beginTransaction();
    try {
        nisha_admin_accounts_lock($pdo);
        $before = [];
        if ($mode === 'create') {
            $q = $pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)'); $q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]); $userId = (int) $pdo->lastInsertId();
        } else {
            $q = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $q->execute([$userId]); if (!$q->fetchColumn()) throw new InvalidArgumentException('That numeric account ID does not exist.');
            $before = nisha_admin_accounts_row($pdo, $userId);
        }
        $security = nisha_admin_accounts_security_lock($pdo, $userId);
        if ($mode === 'revoke') {
            if ($before['is_config_admin']) throw new InvalidArgumentException('Remove this numeric ID from the private administrator configuration before revoking its role.');
            if (!$before['is_admin']) throw new InvalidArgumentException('This account does not have administrator access.');
            if (nisha_admin_accounts_active_admin_count($pdo, $userId) < 1) throw new InvalidArgumentException('Keep at least one active administrator account.');
            $q = $pdo->prepare("DELETE FROM user_roles WHERE user_id=? AND role='admin'"); $q->execute([$userId]);
        } else {
            if ($security['status'] !== 'active') throw new InvalidArgumentException('Restore this account before assigning administrator access.');
            $q = $pdo->prepare("INSERT INTO user_roles(user_id,role,assigned_by) VALUES(?,'admin',NULL) ON DUPLICATE KEY UPDATE role='admin',assigned_by=NULL"); $q->execute([$userId]);
        }
        $q = $pdo->prepare('UPDATE account_security SET auth_version=auth_version+1 WHERE user_id=?'); $q->execute([$userId]);
        $after = nisha_admin_accounts_row($pdo, $userId);
        nisha_admin_accounts_audit($pdo, null, $userId, 'cli_' . $mode . '_admin', 'Private command-line administrator setup.', nisha_admin_accounts_snapshot($before), nisha_admin_accounts_snapshot($after, $mode === 'create'));
        $pdo->commit(); return $userId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') throw new InvalidArgumentException('That email already exists. Inspect its numeric account ID and use the explicit promote command.', 0, $exception);
        throw $exception;
    }
}
