<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/** Public text only. Never store credentials or executable content here. */
function nisha_site_settings(?PDO $pdo = null): array
{
    $settings = ['support_email' => '', 'home_tagline' => 'Your next chapter starts here.', 'announcement_text' => '', 'announcement_enabled' => false];
    try {
        $row = ($pdo ?? db())->query('SELECT support_email,home_tagline,announcement_text,announcement_enabled FROM site_settings WHERE id=1')->fetch();
        if ($row) $settings = array_replace($settings, $row);
    } catch (Throwable $exception) { /* Public learning still works before migration. */ }
    $settings['announcement_enabled'] = (bool) $settings['announcement_enabled'];
    return $settings;
}

function nisha_site_save_settings(PDO $pdo, array $owner, array $input): void
{
    require_once __DIR__ . '/teachers.php';
    require_once __DIR__ . '/admin-accounts.php';
    if (!nisha_is_admin($owner)) throw new InvalidArgumentException('Administrator access is required.');
    $email = is_string($input['support_email'] ?? null) ? strtolower(trim($input['support_email'])) : '';
    if (strlen($email) > 254 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('Enter a valid support email, or leave it blank.');
    $tagline = nisha_admin_accounts_text($input['home_tagline'] ?? null, 'homepage tagline', 2, 180);
    $announcement = nisha_admin_accounts_text($input['announcement_text'] ?? '', 'announcement', 0, 280);
    $enabled = ($input['announcement_enabled'] ?? '') === '1';
    if ($enabled && $announcement === '') throw new InvalidArgumentException('Enter the announcement before publishing it.');
    $settings = ['support_email'=>$email,'home_tagline'=>$tagline,'announcement_text'=>$announcement,'announcement_enabled'=>$enabled];
    $pdo->beginTransaction();
    try {
        nisha_admin_accounts_lock($pdo);
        if (!nisha_admin_accounts_is_admin($pdo, (int)$owner['id']) || nisha_account_security($pdo, (int)$owner['id'])['status'] !== 'active') throw new InvalidArgumentException('Administrator access has changed. Sign in again.');
        $q = $pdo->prepare('INSERT INTO site_settings(id,support_email,home_tagline,announcement_text,announcement_enabled,updated_by) VALUES(1,?,?,?,?,?) ON DUPLICATE KEY UPDATE support_email=VALUES(support_email),home_tagline=VALUES(home_tagline),announcement_text=VALUES(announcement_text),announcement_enabled=VALUES(announcement_enabled),updated_by=VALUES(updated_by)');
        $q->execute([$email,$tagline,$announcement,(int)$enabled,(int)$owner['id']]);
        $q = $pdo->prepare('INSERT INTO site_settings_audit(owner_id,settings_json) VALUES(?,?)');
        $q->execute([(int)$owner['id'], json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}
