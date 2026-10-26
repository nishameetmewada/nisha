<?php
declare(strict_types=1);
require_once __DIR__ . '/mobile-auth.php';

function nisha_mobile_deletion_owner(PDO $pdo, int $ownerId): void
{
    if ($ownerId < 1 || !nisha_admin_accounts_is_admin($pdo, $ownerId) || nisha_account_security($pdo, $ownerId)['status'] !== 'active') {
        throw new InvalidArgumentException('Administrator access is required.');
    }
}

/** A bounded private queue. No password hashes or bearer token material. */
function nisha_mobile_deletion_queue(PDO $pdo, int $ownerId, string $status = 'open', int $page = 1): array
{
    nisha_mobile_deletion_owner($pdo, $ownerId);
    nisha_mobile_ready($pdo);
    if (!in_array($status, ['open','pending','in_progress','completed','all'], true)) $status = 'open';
    $where = $status === 'open' ? " WHERE d.status IN ('pending','in_progress')" : ($status === 'all' ? '' : ' WHERE d.status=?');
    $values = in_array($status, ['open','all'], true) ? [] : [$status];
    $q = $pdo->prepare('SELECT COUNT(*) FROM mobile_deletion_requests d' . $where); $q->execute($values);
    $total = (int) $q->fetchColumn(); $pages = max(1, (int) ceil($total / 25)); $page = max(1, min($pages, $page));
    $q = $pdo->prepare('SELECT d.user_id,d.status,d.reason,d.requested_at,d.resolved_at,d.owner_note,u.name,u.email FROM mobile_deletion_requests d JOIN users u ON u.id=d.user_id' . $where . ' ORDER BY d.requested_at,d.user_id LIMIT 25 OFFSET ' . (($page-1)*25));
    $q->execute($values);
    return ['items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'status'=>$status];
}

function nisha_mobile_deletion_redact_text(string $text, string $name, string $email): string
{
    if ($email !== '') $text = str_ireplace($email, '[removed email]', $text);
    if ($name !== '') $text = preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($name, '/') . '(?![\p{L}\p{N}])/iu', '[removed name]', $text) ?? '[Personal text removed]';
    return $text;
}

/** Remove this identity from ordinary account-change snapshots, not other users. */
function nisha_mobile_deletion_redact_snapshot(mixed $value, int $userId, string $name, string $email): mixed
{
    if (is_array($value)) {
        if ((int)($value['id'] ?? 0) === $userId) {
            if (array_key_exists('name', $value)) $value['name'] = 'Deleted account';
            if (array_key_exists('email', $value)) $value['email'] = 'closed-' . $userId . '@example.invalid';
        }
        foreach ($value as $key => $part) {
            if (in_array($key, ['password_hash','password','new_password','current_password'], true)) { unset($value[$key]); continue; }
            $value[$key] = nisha_mobile_deletion_redact_snapshot($part, $userId, $name, $email);
        }
        return $value;
    }
    return is_string($value) ? nisha_mobile_deletion_redact_text($value, $name, $email) : $value;
}

/**
 * Completion really removes private learning data and anonymises the retained
 * account. Financial and moderation references keep their original numeric IDs.
 */
function nisha_mobile_deletion_action(PDO $pdo, int $ownerId, int $userId, string $action, array $input): array
{
    nisha_admin_accounts_ready($pdo); nisha_mobile_ready($pdo);
    if (!in_array($action, ['begin','complete'], true) || $ownerId < 1 || $userId < 1) throw new InvalidArgumentException('Choose a valid deletion request action.');
    $reason = nisha_admin_accounts_text($input['reason'] ?? null, 'processing note', 5, 500);
    $password = $input['current_password'] ?? null;
    if (!is_string($password) || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) throw new InvalidArgumentException('Enter your current administrator password.');
    if ($action === 'complete' && ($input['confirmation'] ?? false) !== true && ($input['confirmation'] ?? '') !== '1') throw new InvalidArgumentException('Confirm removal and anonymisation before completing this request.');
    if ($pdo->inTransaction()) throw new LogicException('Deletion processing requires a separate transaction.');
    $pdo->beginTransaction();
    try {
        nisha_admin_accounts_lock($pdo);
        nisha_admin_accounts_reauthenticate_locked($pdo, $ownerId, $password);
        $q = $pdo->prepare('SELECT id,name,email FROM users WHERE id=? FOR UPDATE'); $q->execute([$userId]); $user = $q->fetch();
        if (!$user) throw new InvalidArgumentException('The selected account no longer exists.');
        $security = nisha_admin_accounts_security_lock($pdo, $userId);
        $q = $pdo->prepare('SELECT * FROM mobile_deletion_requests WHERE user_id=? FOR UPDATE'); $q->execute([$userId]); $request = $q->fetch();
        if (!$request) throw new InvalidArgumentException('There is no confirmed deletion request for this account.');
        if ($request['status'] === 'completed') { $pdo->commit(); return ['status'=>'completed','already_completed'=>true,'user_id'=>$userId]; }
        if (!in_array($request['status'], ['pending','in_progress'], true)) throw new InvalidArgumentException('Only an open, confirmed request can be processed.');
        if ($userId === $ownerId) throw new InvalidArgumentException('Another administrator must process your request. Keep an active owner available.');
        if (in_array($userId, nisha_admin_accounts_configured_ids(), true)) throw new InvalidArgumentException('Remove this numeric ID from the private administrator configuration before processing deletion.');
        if (nisha_admin_accounts_is_admin($pdo, $userId) && $security['status'] === 'active' && nisha_admin_accounts_active_admin_count($pdo, $userId) < 1) throw new InvalidArgumentException('Keep at least one active administrator.');
        $note = nisha_mobile_deletion_redact_text($reason, $user['name'], $user['email']);
        if ($action === 'begin') {
            $q = $pdo->prepare("UPDATE mobile_deletion_requests SET status='in_progress',owner_note=? WHERE user_id=?"); $q->execute([$note,$userId]);
            nisha_admin_accounts_audit($pdo, $ownerId, $userId, 'begin_deletion', $note, ['id'=>$userId,'deletion_status'=>$request['status']], ['id'=>$userId,'deletion_status'=>'in_progress']);
            $pdo->commit(); return ['status'=>'in_progress','user_id'=>$userId];
        }
        $removed = [];
        foreach (['attempts','exam_runs','ai_reviews','coaching_watch_presence','coaching_watch_sessions','mobile_access_tokens'] as $table) {
            $q = $pdo->prepare('DELETE FROM ' . $table . ' WHERE user_id=?'); $q->execute([$userId]); $removed[$table] = $q->rowCount();
        }
        $q = $pdo->prepare('DELETE FROM ai_review_usage WHERE scope_key=?'); $q->execute(['user-' . $userId]);
        // Published teacher material disappears from catalogues; ledger IDs stay.
        $q = $pdo->prepare("UPDATE teacher_profiles SET display_name='Deleted teacher',bio='',status='rejected',review_note='Account deletion completed.' WHERE user_id=?"); $q->execute([$userId]);
        $q = $pdo->prepare("UPDATE coaching_videos SET title='Removed lesson',description='',video_url='',status='rejected',review_note='Account deletion completed.',revision=revision+1 WHERE teacher_id=?"); $q->execute([$userId]);
        $q = $pdo->prepare("UPDATE content_publications p JOIN content_review_versions v ON v.id=p.version_id SET p.status='withdrawn' WHERE v.reviewer_id=?"); $q->execute([$userId]);
        $q = $pdo->prepare('SELECT id,target_id,before_json,after_json,reason FROM admin_account_audit WHERE target_id=? OR actor_id=? FOR UPDATE'); $q->execute([$userId,$userId]);
        foreach ($q->fetchAll() as $entry) {
            $snapshots = [];
            foreach (['before_json','after_json'] as $field) {
                $snapshot = json_decode($entry[$field], true);
                if (!is_array($snapshot)) $snapshot = ['personal_text_removed'=>true];
                $snapshots[] = json_encode(nisha_mobile_deletion_redact_snapshot($snapshot, $userId, $user['name'], $user['email']), JSON_THROW_ON_ERROR);
            }
            $update = $pdo->prepare('UPDATE admin_account_audit SET before_json=?,after_json=?,reason=? WHERE id=?');
            $update->execute([$snapshots[0],$snapshots[1],nisha_mobile_deletion_redact_text($entry['reason'],$user['name'],$user['email']),$entry['id']]);
        }
        $q = $pdo->prepare('UPDATE site_settings SET support_email=\'\' WHERE support_email=?'); $q->execute([$user['email']]);
        $q = $pdo->prepare('DELETE FROM user_roles WHERE user_id=?'); $q->execute([$userId]);
        $q = $pdo->prepare("UPDATE account_security SET status='suspended',auth_version=auth_version+1,reauth_failures=0,reauth_blocked_until=NULL WHERE user_id=?"); $q->execute([$userId]);
        $closedEmail = 'closed-'.$userId.'@example.invalid';
        $q = $pdo->prepare('SELECT 1 FROM users WHERE email=? AND id<>?'); $q->execute([$closedEmail,$userId]);
        if ($q->fetchColumn()) $closedEmail = 'closed-'.$userId.'-'.bin2hex(random_bytes(12)).'@example.invalid';
        $q = $pdo->prepare('UPDATE users SET name=?,email=?,password_hash=? WHERE id=?');
        $q->execute(['Deleted account',$closedEmail,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$userId]);
        $q = $pdo->prepare('UPDATE student_entitlements SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()) WHERE user_id=?'); $q->execute([$userId]);
        $q = $pdo->prepare("UPDATE mobile_deletion_requests SET status='completed',reason='',resolved_at=UTC_TIMESTAMP(),owner_note=? WHERE user_id=?"); $q->execute([$note,$userId]);
        nisha_admin_accounts_audit($pdo, $ownerId, $userId, 'fulfill_deletion', $note, ['id'=>$userId,'deletion_status'=>$request['status']], ['id'=>$userId,'deletion_status'=>'completed','personal_data_removed'=>true,'removed'=>$removed,'financial_references_retained'=>true]);
        $pdo->commit();
        return ['status'=>'completed','user_id'=>$userId,'removed'=>$removed,'financial_references_retained'=>true];
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}
