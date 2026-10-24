<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/teachers.php';
require_once __DIR__ . '/includes/admin-accounts.php';
require_once __DIR__ . '/includes/site-settings.php';
$owner = require_login(); $isOwner = nisha_is_admin($owner);
header('Cache-Control: private, no-store');
if (!$isOwner) http_response_code(403);
$error = ''; $audit = [];
if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { http_response_code(403); $error = 'Your session expired. Refresh this page before saving.'; }
    else try { nisha_site_save_settings(db(), $owner, $_POST); redirect('admin-settings.php?saved=1'); }
    catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { $error = 'Website settings could not be saved. Check the updated database schema and try again.'; }
}
$settings = nisha_site_settings();
if ($isOwner) try { $audit = db()->query('SELECT owner_id,settings_json,created_at FROM site_settings_audit ORDER BY id DESC LIMIT 10')->fetchAll(); } catch (Throwable $exception) {}
$pageTitle = 'Website settings'; $activePage = 'admin'; $extraStyles = ['assets/css/teachers.css','assets/css/admin.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container teacher-page admin-page">
<?php if (!$isOwner): ?><h1>Administrator access is required.</h1><p>This account cannot change website settings.</p><a class="btn btn-primary" href="dashboard.php">Return to my dashboard</a>
<?php else: ?>
    <div class="teacher-intro"><div><p class="eyebrow">YOUR NISHA WEBSITE</p><h1>Website settings.</h1><p>Update the homepage tagline, student support contact and public announcement.</p><nav class="teacher-nav" aria-label="Admin pages"><a class="btn btn-outline" href="admin.php">Admin dashboard</a><a class="btn btn-outline" href="admin-accounts.php">Accounts</a><a class="btn btn-outline" href="billing-settings.php">Pricing</a><a class="btn btn-outline" href="admin-promotions.php">Promotions</a></nav></div></div>
    <?php if ($error): ?><div class="teacher-notice is-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (($_GET['saved'] ?? '') === '1' && !$error): ?><div class="teacher-notice" role="status">Website settings saved.</div><?php endif; ?>
    <section class="teacher-panel"><h2>Public website details</h2><form method="post" class="teacher-form"><?= csrf_field() ?>
        <label class="teacher-field">Homepage tagline<input name="home_tagline" minlength="2" maxlength="180" required value="<?= e($settings['home_tagline']) ?>"></label>
        <label class="teacher-field">Student support email<input name="support_email" type="email" maxlength="254" value="<?= e($settings['support_email']) ?>"><small>Displayed as a contact link in the footer. Leave blank to hide it.</small></label>
        <label class="teacher-field">Public announcement<input name="announcement_text" maxlength="280" value="<?= e($settings['announcement_text']) ?>"><small>Plain text shown on every page when enabled.</small></label>
        <label class="teacher-check"><input name="announcement_enabled" type="checkbox" value="1" <?= $settings['announcement_enabled']?'checked':'' ?>><span>Publish this announcement.</span></label>
        <button type="submit" class="btn btn-primary">Save website settings</button>
    </form></section>
    <section class="teacher-panel"><h2>Recent website changes</h2><?php if (!$audit): ?><p>No website changes have been recorded yet.</p><?php else: ?><div class="teacher-list"><?php foreach ($audit as $entry): $saved = json_decode($entry['settings_json'],true) ?: []; ?><article class="teacher-list-item"><p><strong><?= e($entry['created_at']) ?> UTC</strong> · Admin <?= (int)$entry['owner_id'] ?></p><p><?= e($saved['home_tagline'] ?? '') ?> · Support: <?= e($saved['support_email'] ?: 'Not displayed') ?></p><p>Announcement <?= !empty($saved['announcement_enabled'])?'published':'hidden' ?>: <?= e($saved['announcement_text'] ?? '') ?></p></article><?php endforeach; ?></div><?php endif; ?></section>
<?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
