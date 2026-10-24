<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/teachers.php';
$owner = require_login();
$isOwner = nisha_is_admin($owner);
header('Cache-Control: private, no-store');
if (!$isOwner) http_response_code(403);
$error = '';
if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Your session expired. Refresh before saving the billing settings.';
        http_response_code(403);
    } else {
        try {
            nisha_commerce_save_plan(db(), $owner, $_POST);
            redirect('billing-settings.php?saved=1');
        } catch (InvalidArgumentException|NishaCommerceException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            $error = 'Billing settings could not be saved. Import the commerce migration and try again.';
        }
    }
}
$settings = nisha_commerce_settings();
$audit = [];
if ($isOwner) {
    try { $audit = db()->query('SELECT amount_minor,currency,access_days,checkout_enabled,created_at FROM commerce_settings_audit ORDER BY id DESC LIMIT 10')->fetchAll(); }
    catch (Throwable $exception) { /* A missing optional migration is explained on save. */ }
}
$pageTitle = 'Billing settings';
$activePage = 'admin';
$extraStyles = ['assets/css/account.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container account-page">
<?php if (!$isOwner): ?>
    <div class="account-intro"><div><p class="eyebrow">OWNER ACCESS</p><h1>This page is for the site owner.</h1><p class="muted">Your student account does not have permission to change prices or payment settings.</p><a class="btn btn-primary" href="dashboard.php">Return to your dashboard →</a></div></div>
<?php else: ?>
    <div class="account-intro"><div><p class="eyebrow">YOUR BUSINESS SETTINGS</p><h1>Set the price. Keep control.</h1><p class="muted">Students receive one free completed full mock, then can purchase a one-time access pass. There is no automatic renewal.</p></div><a class="btn btn-outline" href="admin.php">Owner portal →</a></div>
    <?php if ($error): ?><div class="setup-notice" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (($_GET['saved'] ?? '') === '1' && !$error): ?><div class="setup-notice" role="status">Billing settings saved. Existing orders retain the price and duration agreed at checkout.</div><?php endif; ?>
    <section class="dashboard-panel">
        <h2>Your access pass</h2>
        <p>Changing this price affects future checkout sessions. An active pass cannot be purchased again until it expires; passes do not stack.</p>
        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            <label for="billing-amount">Price in the selected currency</label>
            <input id="billing-amount" name="amount" type="text" inputmode="decimal" required maxlength="9" value="<?= e(number_format((int) $settings['amount_minor'] / 100, 2, '.', '')) ?>">
            <label for="billing-currency">Currency</label>
            <select id="billing-currency" name="currency">
                <?php foreach (['aud'=>'AUD — Australian dollar','nzd'=>'NZD — New Zealand dollar','usd'=>'USD — US dollar','gbp'=>'GBP — Pound sterling','eur'=>'EUR — Euro','inr'=>'INR — Indian rupee','cad'=>'CAD — Canadian dollar','sgd'=>'SGD — Singapore dollar'] as $code => $label): ?><option value="<?= e($code) ?>" <?= $settings['currency'] === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <label for="billing-days">Access period in days</label>
            <input id="billing-days" name="access_days" type="number" min="1" max="3650" required value="<?= (int) $settings['access_days'] ?>">
            <label><input name="checkout_enabled" type="checkbox" value="1" <?= $settings['checkout_requested'] ? 'checked' : '' ?>> Enable new checkout sessions</label>
            <button class="btn btn-primary" type="submit">Save billing settings</button>
        </form>
    </section>
    <section class="dashboard-panel">
        <h2>Payment connection</h2>
        <p><?= $settings['provider_ready'] ? 'The required private connection settings are present. A provider checkout and signed webhook still need to be tested before accepting real payments.' : 'Add the private Stripe secret key, webhook signing secret and HTTPS site URL to config/config.php before enabling checkout.' ?></p>
        <p>New checkout is <strong><?= $settings['enabled'] ? 'enabled' : 'disabled' ?></strong>. Signed payment and refund updates continue to be processed when new checkout is paused.</p>
        <p>Configure the webhook endpoint as <code>/api/stripe-webhook.php</code>. Keep keys and signing secrets in the private server configuration. This page never displays their values. Explicit NISHA_PAYMENT_* environment settings override saved price settings.</p>
    </section>
    <?php if ($audit): ?><section class="dashboard-panel"><h2>Recent billing changes</h2><div class="attempts-table-wrap"><table class="attempts-table"><caption class="sr-only">Ten most recent owner billing changes</caption><thead><tr><th>Price</th><th>Access</th><th>New checkout</th><th>Date (UTC)</th></tr></thead><tbody><?php foreach ($audit as $row): ?><tr><td><?= e(nisha_commerce_money((int) $row['amount_minor'], $row['currency'])) ?></td><td><?= (int) $row['access_days'] ?> days</td><td><?= $row['checkout_enabled'] ? 'Enabled' : 'Disabled' ?></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
