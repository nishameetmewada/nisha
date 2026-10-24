<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/teachers.php';
require_once __DIR__ . '/includes/admin-accounts.php';
$owner = require_login();
$allowed = nisha_is_admin($owner);
header('Cache-Control: private, no-store');
if (!$allowed) http_response_code(403);
$error = ''; $unavailable = false; $selected = null;
$query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$page = is_string($_GET['page'] ?? null) && ctype_digit($_GET['page']) ? max(1, min(100000, (int) $_GET['page'])) : 1;
$target = is_string($_GET['id'] ?? null) && preg_match('/\A[1-9][0-9]{0,17}\z/D', $_GET['id']) ? (int) $_GET['id'] : 0;
$catalog = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
if ($allowed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Your session expired. Refresh the page before making changes.'; http_response_code(403); }
    else {
        try {
            $target = nisha_teacher_positive_id($_POST['user_id'] ?? null, 'student account');
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            if (in_array($action, ['grant_admin', 'revoke_admin'], true) && ($_POST['confirmation'] ?? '') !== '1') throw new InvalidArgumentException('Confirm the administrator-role change for this exact account.');
            nisha_admin_accounts_change(db(), (int) $owner['id'], $target, $action, $_POST);
            // Changes to the current account may revoke this session. Re-enter
            // through the normal authentication checks on the next request.
            redirect('admin-accounts.php?' . http_build_query(['id' => $target, 'saved' => '1']));
        } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
        catch (Throwable $exception) { $error = 'This account change could not be confirmed. Check the saved account before trying again. Account controls require the latest database migration.'; }
    }
}
if ($allowed) {
    try {
        $catalog = nisha_admin_accounts_search(db(), $query, $page);
        if ($target > 0) $selected = nisha_admin_accounts_detail(db(), $target);
        if ($target > 0 && !$selected && !$error) $error = 'That account could not be found.';
    } catch (InvalidArgumentException $exception) { if (!$error) $error = $exception->getMessage(); }
    catch (Throwable $exception) { $unavailable = true; }
}
$pageTitle = 'Manage accounts'; $activePage = 'admin'; $extraStyles = ['assets/css/teachers.css', 'assets/css/admin.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container teacher-page admin-page">
<?php if (!$allowed): ?>
    <h1>Administrator access is required.</h1><p>This account cannot manage other accounts.</p><a class="btn btn-primary" href="dashboard.php">Return to my dashboard →</a>
<?php else: ?>
    <div class="teacher-intro"><div><p class="eyebrow">NISHA ADMINISTRATION</p><h1>Manage accounts.</h1><p>Find an account, correct its details, reset its password or manage access. Every change requires your current administrator password and a reason, and is recorded in the audit log.</p><nav class="teacher-nav" aria-label="Admin pages"><a class="btn btn-outline" href="admin.php">Admin dashboard</a><a class="btn btn-outline" href="billing-settings.php">Pricing</a><a class="btn btn-outline" href="admin-promotions.php">Discounts &amp; promotions</a><a class="btn btn-outline" href="admin-settings.php">Website settings</a></nav></div></div>
    <?php if ($error): ?><div class="teacher-notice is-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (($_GET['saved'] ?? '') === '1' && !$error): ?><div class="teacher-notice" role="status">Account change saved. Security changes sign the affected account out of existing sessions.</div><?php endif; ?>
    <?php if ($unavailable): ?><div class="teacher-notice" role="status">Account administration needs database/migrations/008_admin_accounts.sql. Import the migration into your existing NISHA database, preserving accounts and results.</div><?php else: ?>
    <section class="teacher-panel" aria-labelledby="account-search-title"><h2 id="account-search-title">Find an account</h2>
        <form method="get" class="admin-search"><label class="teacher-field" for="account-search">Name, email or account ID<input id="account-search" name="q" maxlength="100" value="<?= e($query) ?>" placeholder="Search accounts"></label><button class="btn btn-primary" type="submit">Search accounts →</button></form>
        <p class="teacher-help"><?= number_format((int) $catalog['total']) ?> matching accounts. Student results and payment ledgers are retained when access is suspended.</p>
        <?php if (!$catalog['items']): ?><p>No matching accounts.</p><?php else: ?><div class="teacher-table-wrap"><table class="teacher-table admin-accounts-table"><caption>Registered NISHA accounts</caption><thead><tr><th>Account</th><th>Access</th><th>Joined (UTC)</th><th>Action</th></tr></thead><tbody><?php foreach ($catalog['items'] as $account): ?><tr><td><strong><?= e($account['name']) ?></strong><br><span><?= e($account['email']) ?></span><br><small>ID <?= (int) $account['id'] ?></small></td><td><?= e(ucfirst($account['status'])) ?><br><small><?= !empty($account['is_admin']) ? 'Administrator' : 'Student' ?><?= !empty($account['teacher_status']) ? ' · Teacher ' . e($account['teacher_status']) : '' ?></small></td><td><?= e(substr($account['created_at'], 0, 10)) ?></td><td><a class="text-link" href="admin-accounts.php?<?= e(http_build_query(['id' => $account['id'], 'q' => $query, 'page' => $catalog['page']])) ?>#account-details">Manage →</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <?php if ($catalog['pages'] > 1): ?><nav class="admin-pagination" aria-label="Account search pages"><?php if ($catalog['page'] > 1): ?><a href="?<?= e(http_build_query(['q' => $query, 'page' => $catalog['page'] - 1])) ?>">← Previous</a><?php endif; ?><span>Page <?= (int) $catalog['page'] ?> of <?= (int) $catalog['pages'] ?></span><?php if ($catalog['page'] < $catalog['pages']): ?><a href="?<?= e(http_build_query(['q' => $query, 'page' => $catalog['page'] + 1])) ?>">Next →</a><?php endif; ?></nav><?php endif; ?>
    </section>
    <?php if ($selected): ?>
    <section class="teacher-panel" id="account-details" aria-labelledby="account-details-title"><div class="admin-section-heading"><div><p class="eyebrow">ACCOUNT <?= (int) $selected['id'] ?></p><h2 id="account-details-title"><?= e($selected['name']) ?></h2><p><?= e($selected['email']) ?></p></div><span class="teacher-status"><?= e($selected['status']) ?></span></div><p class="teacher-help">Existing passwords are never displayed. Changes to your own email or password can sign you out. You cannot suspend yourself or remove your own administrator access.</p>
        <div class="admin-detail-grid">
            <form method="post" class="teacher-form admin-form" id="account-profile-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="action" value="edit_profile"><h3>Profile details</h3><label class="teacher-field">Name<input name="name" autocomplete="off" minlength="2" maxlength="100" required value="<?= e($selected['name']) ?>"></label><label class="teacher-field">Email address<input type="email" name="email" autocomplete="off" maxlength="254" required value="<?= e($selected['email']) ?>"></label><label class="teacher-field">Reason for this change<input name="reason" minlength="5" maxlength="500" required></label><label class="teacher-field">Your administrator password<input name="current_password" type="password" autocomplete="current-password" required></label><button class="btn btn-primary" type="submit">Save profile details</button></form>
            <form method="post" class="teacher-form admin-form" id="account-password-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="action" value="reset_password"><h3>Set a new password</h3><p class="teacher-help">Give the account owner their new password through a private channel. This site does not send password-reset emails.</p><label class="teacher-field">New account password<input name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label class="teacher-field">Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label class="teacher-field">Reason for this change<input name="reason" minlength="5" maxlength="500" required></label><label class="teacher-field">Your administrator password<input name="current_password" type="password" autocomplete="current-password" required></label><button class="btn btn-primary" type="submit">Set new password</button></form>
            <form method="post" class="teacher-form admin-form" id="account-status-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="action" value="<?= $selected['status'] === 'suspended' ? 'restore' : 'suspend' ?>"><h3><?= $selected['status'] === 'suspended' ? 'Restore account access' : 'Suspend account access' ?></h3><p class="teacher-help"><?= $selected['status'] === 'suspended' ? 'Allow this student to sign in again.' : 'Block sign-in and revoke current sessions. Saved results, payment records and teacher ledgers are retained.' ?></p><label class="teacher-field">Reason for this change<input name="reason" minlength="5" maxlength="500" required></label><label class="teacher-field">Your administrator password<input name="current_password" type="password" autocomplete="current-password" required></label><button class="btn btn-outline" type="submit" <?= (int) $selected['id'] === (int) $owner['id'] ? 'disabled' : '' ?>><?= $selected['status'] === 'suspended' ? 'Restore access' : 'Suspend account' ?></button></form>
            <form method="post" class="teacher-form admin-form" id="account-role-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="action" value="<?= !empty($selected['is_admin']) ? 'revoke_admin' : 'grant_admin' ?>"><h3>Administrator access</h3><p class="teacher-help"><?= !empty($selected['is_admin']) ? 'This account can manage students, business settings and teacher controls.' : 'Granting administrator access gives this account control over students, prices, promotions and business settings.' ?><?= !empty($selected['is_config_admin']) ? ' This administrator is protected by private server configuration; remove its numeric ID there before revoking the role.' : '' ?></p><label class="teacher-field">Reason for this change<input name="reason" minlength="5" maxlength="500" required></label><label class="teacher-field">Your administrator password<input name="current_password" type="password" autocomplete="current-password" required></label><label class="teacher-check"><input type="checkbox" name="confirmation" value="1" required><span>I intend to <?= !empty($selected['is_admin']) ? 'remove' : 'grant' ?> administrator control for this exact account.</span></label><button class="btn btn-outline" type="submit" <?= !empty($selected['is_admin']) && ((int) $selected['id'] === (int) $owner['id'] || !empty($selected['is_config_admin'])) ? 'disabled' : '' ?>><?= !empty($selected['is_admin']) ? 'Remove administrator role' : 'Grant administrator role' ?></button></form>
        </div>
    </section>
    <section class="teacher-panel" id="account-audit"><h2>Account change history</h2><?php if (empty($selected['audit'])): ?><p>No administrator changes have been recorded for this account.</p><?php else: ?><div class="teacher-table-wrap"><table class="teacher-table"><caption>Recent account administration actions</caption><thead><tr><th>Action</th><th>Reason</th><th>Administrator</th><th>Date (UTC)</th></tr></thead><tbody><?php foreach ($selected['audit'] as $entry): ?><tr><td><?= e(str_replace('_', ' ', $entry['action'])) ?></td><td><?= e($entry['reason']) ?></td><td>ID <?= (int) $entry['actor_id'] ?></td><td><?= e($entry['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
    <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
