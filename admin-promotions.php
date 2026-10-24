<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/teachers.php';
require_once __DIR__ . '/includes/promotions.php';
$owner = require_login(); $allowed = nisha_is_admin($owner);
header('Cache-Control: private, no-store');
if (!$allowed) http_response_code(403);
$error = ''; $unavailable = false; $promotions = [];
$selectedId = is_string($_GET['id'] ?? null) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;
$form = ['id' => 0, 'code' => '', 'label' => '', 'type' => 'percent', 'percent' => 10, 'fixed_amount' => '', 'currency' => 'aud', 'starts_at' => '', 'ends_at' => '', 'max_redemptions' => '', 'max_per_student' => 1, 'enabled' => 0, 'featured' => 0];
if ($allowed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Your session expired. Refresh the page before changing promotions.'; http_response_code(403); }
    else try {
        if (($_POST['action'] ?? '') === 'toggle') {
            $selectedId = nisha_teacher_positive_id($_POST['id'] ?? null, 'promotion');
            nisha_promotions_set_enabled(db(), $owner, $selectedId, ($_POST['enabled'] ?? '') === '1');
        } elseif (($_POST['action'] ?? '') === 'save') {
            $selectedId = nisha_promotions_save(db(), $owner, $_POST);
        } else throw new InvalidArgumentException('Choose a valid promotion action.');
        redirect('admin-promotions.php?' . http_build_query(['id' => $selectedId, 'saved' => '1']));
    } catch (InvalidArgumentException|NishaCommerceException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { $error = 'This promotion change could not be confirmed. Check the saved promotion before trying again. The promotions migration may need to be imported.'; }
}
if ($allowed) {
    try {
        $promotions = array_map(static function (array $row): array {
            $row['label'] = $row['public_label'];
            $row['type'] = $row['discount_type'];
            $row['percent'] = $row['discount_type'] === 'percent' ? (int) $row['discount_value'] : '';
            $row['fixed_amount_minor'] = $row['discount_type'] === 'fixed' ? (int) $row['discount_value'] : 0;
            $row['used_count'] = (int) $row['reserved_count'] + (int) $row['redeemed_count'];
            return $row;
        }, nisha_promotions_list(db(), $owner));
        foreach ($promotions as $promotion) if ((int) $promotion['id'] === $selectedId) {
            $form = array_replace($form, $promotion);
            if (isset($promotion['fixed_amount_minor'])) $form['fixed_amount'] = number_format((int) $promotion['fixed_amount_minor'] / 100, 2, '.', '');
        }
    } catch (Throwable $exception) { $unavailable = true; }
}
if ($error && ($_POST['action'] ?? '') === 'save') foreach (array_keys($form) as $key) {
    if (in_array($key, ['enabled', 'featured'], true)) $form[$key] = ($_POST[$key] ?? '') === '1' ? 1 : 0;
    elseif (is_string($_POST[$key] ?? null)) $form[$key] = $_POST[$key];
}
$dateInput = static fn ($value): string => is_string($value) && $value !== '' ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
$pageTitle = 'Discounts and promotions'; $activePage = 'admin'; $extraStyles = ['assets/css/teachers.css', 'assets/css/admin.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container teacher-page admin-page">
<?php if (!$allowed): ?>
    <h1>Administrator access is required.</h1><p>This account cannot change promotions.</p><a class="btn btn-primary" href="dashboard.php">Return to my dashboard →</a>
<?php else: ?>
    <div class="teacher-intro"><div><p class="eyebrow">NISHA ADMINISTRATION</p><h1>Discounts &amp; promotions.</h1><p>Create time-limited discount codes and choose which offer students see. Discounts change future checkout prices; existing orders keep the price agreed when they were created.</p><nav class="teacher-nav" aria-label="Admin pages"><a class="btn btn-outline" href="admin.php">Admin dashboard</a><a class="btn btn-outline" href="billing-settings.php">Base pricing</a><a class="btn btn-outline" href="admin-accounts.php">Manage accounts</a><a class="btn btn-outline" href="admin-settings.php">Website settings</a></nav></div></div>
    <?php if ($error): ?><div class="teacher-notice is-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (($_GET['saved'] ?? '') === '1' && !$error): ?><div class="teacher-notice" role="status">Promotion saved. The checkout checks dates, currency and remaining availability before applying a code.</div><?php endif; ?>
    <?php if ($unavailable): ?><div class="teacher-notice" role="status">Promotion controls need database/migrations/007_promotions.sql. Import the migration into the existing NISHA database, preserving accounts, orders and results.</div><?php else: ?>
    <section class="teacher-panel" aria-labelledby="promotion-form-title"><div class="admin-section-heading"><h2 id="promotion-form-title"><?= (int) $form['id'] > 0 ? 'Edit promotion' : 'Create a promotion' ?></h2><?php if ((int) $form['id'] > 0): ?><a class="text-link" href="admin-promotions.php">Create another code →</a><?php endif; ?></div>
        <form method="post" class="teacher-form" id="promotion-form"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
            <div class="teacher-row"><label class="teacher-field">Discount code<input id="promotion-code" name="code" minlength="3" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,39}" autocomplete="off" required value="<?= e($form['code']) ?>" placeholder="WELCOME10"><small>Start with a letter or number; underscores and hyphens are also allowed. Codes are matched without case sensitivity.</small></label><label class="teacher-field">Student-facing offer label<input id="promotion-label" name="label" maxlength="120" required value="<?= e($form['label']) ?>" placeholder="A little help for your next chapter"></label></div>
            <div class="teacher-row"><label class="teacher-field">Discount type<select name="type" id="promotion-type"><option value="percent" <?= $form['type'] === 'percent' ? 'selected' : '' ?>>Percentage discount</option><option value="fixed" <?= $form['type'] === 'fixed' ? 'selected' : '' ?>>Fixed amount discount</option></select></label><label class="teacher-field">Offer currency<select name="currency" id="promotion-currency"><?php foreach (['aud', 'nzd', 'usd', 'eur', 'gbp', 'inr', 'cad', 'sgd'] as $currency): ?><option value="<?= e($currency) ?>" <?= strtolower((string) $form['currency']) === $currency ? 'selected' : '' ?>><?= e(strtoupper($currency)) ?></option><?php endforeach; ?></select><small>Choose the same currency as the access pass.</small></label></div>
            <div class="teacher-row"><label class="teacher-field">Percentage off<input id="promotion-percent" name="percent" type="number" min="1" max="99" value="<?= e($form['percent']) ?>"><small>Used only for percentage discounts: 1–99%.</small></label><label class="teacher-field">Fixed amount off<input id="promotion-fixed-amount" name="fixed_amount" inputmode="decimal" maxlength="12" value="<?= e($form['fixed_amount']) ?>" placeholder="5.00"><small>Used only for fixed discounts, in the selected currency.</small></label></div>
            <div class="teacher-row"><label class="teacher-field">Starts at (UTC)<input id="promotion-start" name="starts_at" type="datetime-local" value="<?= e($dateInput($form['starts_at'])) ?>"><small>Leave blank to allow immediate use.</small></label><label class="teacher-field">Ends at (UTC)<input id="promotion-end" name="ends_at" type="datetime-local" value="<?= e($dateInput($form['ends_at'])) ?>"><small>Leave blank for no scheduled end.</small></label></div>
            <div class="teacher-row"><label class="teacher-field">Total redemption limit<input name="max_redemptions" type="number" min="1" max="100000000" value="<?= e($form['max_redemptions']) ?>"><small>Leave blank for no limit. Pending checkout reservations also count until they expire.</small></label><label class="teacher-field">Redemptions per student<input name="max_per_student" type="number" min="1" max="1000000" value="<?= e($form['max_per_student']) ?>"><small>Leave blank for no per-account limit.</small></label></div>
            <label class="teacher-check"><input type="checkbox" name="enabled" value="1" <?= $form['enabled'] ? 'checked' : '' ?>><span>Enable this discount code for eligible new checkout sessions.</span></label>
            <label class="teacher-check"><input type="checkbox" name="featured" value="1" <?= $form['featured'] ? 'checked' : '' ?>><span>Feature this offer publicly when it is active and available.</span></label>
            <p class="teacher-help">Only one code applies to a checkout. A discount must leave a valid positive payment amount; it does not create a free-access grant. Stripe configuration and checkout must be enabled before students can pay.</p><button type="submit" class="btn btn-primary">Save promotion</button>
        </form>
    </section>
    <section class="teacher-panel" id="promotion-list"><h2>Your promotions</h2><?php if (!$promotions): ?><p>No promotions have been created yet.</p><?php else: ?><div class="teacher-table-wrap"><table class="teacher-table"><caption>Configured discount codes and usage</caption><thead><tr><th>Code &amp; offer</th><th>Discount</th><th>Availability (UTC)</th><th>Limits</th><th>Manage</th></tr></thead><tbody><?php foreach ($promotions as $promotion): ?><tr><td><strong><?= e($promotion['code']) ?></strong><br><?= e($promotion['label']) ?><br><small><?= !empty($promotion['enabled']) ? 'Enabled' : 'Disabled' ?><?= !empty($promotion['featured']) ? ' · Featured' : '' ?></small></td><td><?= $promotion['type'] === 'percent' ? (int) $promotion['percent'] . '%' : e(nisha_commerce_money((int) ($promotion['fixed_amount_minor'] ?? 0), $promotion['currency'])) ?><br><small><?= e(strtoupper($promotion['currency'])) ?></small></td><td><?= e($promotion['starts_at'] ?: 'Immediate start') ?><br><?= e($promotion['ends_at'] ?: 'No scheduled end') ?></td><td><?= !empty($promotion['max_redemptions']) ? (int) $promotion['max_redemptions'] . ' total' : 'No total limit' ?><br><?= !empty($promotion['max_per_student']) ? (int) $promotion['max_per_student'] . ' per student' : 'No per-student limit' ?><?php if (isset($promotion['used_count'])): ?><br><small><?= (int) $promotion['used_count'] ?> used/reserved</small><?php endif; ?></td><td><a class="text-link" href="admin-promotions.php?id=<?= (int) $promotion['id'] ?>#promotion-form-title">Edit →</a><form method="post" class="admin-inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $promotion['id'] ?>"><input type="hidden" name="enabled" value="<?= !empty($promotion['enabled']) ? '0' : '1' ?>"><button type="submit" class="btn btn-outline btn-small"><?= !empty($promotion['enabled']) ? 'Disable' : 'Enable' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
    <?php endif; ?>
<?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
