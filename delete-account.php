<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/mobile-auth.php';
header('Cache-Control: private, no-store');
$receipt = is_array($_SESSION['deletion_receipt'] ?? null) ? $_SESSION['deletion_receipt'] : null;
unset($_SESSION['deletion_receipt']);
$user = $receipt ? null : require_login(); $error = '';
if ($receipt) http_response_code(202);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { http_response_code(403); $error = 'Your session expired. Refresh this page before submitting.'; }
    else try {
        $receipt = nisha_mobile_deletion_request(db(), $user, ['password'=>$_POST['password'] ?? '', 'confirmation'=>($_POST['confirmation'] ?? '') === '1','reason'=>$_POST['reason'] ?? ''], (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $_SESSION = []; session_regenerate_id(true); $_SESSION['deletion_receipt'] = $receipt;
        redirect('delete-account.php');
    } catch (NishaMobileException $exception) { http_response_code($exception->httpStatus); $error = $exception->publicMessage; }
    catch (Throwable $exception) { http_response_code(503); $error = 'Your request could not be confirmed. Check the account setup and try again.'; }
}
$pageTitle = 'Request account deletion'; $activePage = 'account'; $extraStyles = ['assets/css/account.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container account-page">
    <div class="account-intro"><div><p class="eyebrow">YOUR ACCOUNT. YOUR CHOICE.</p><h1>Request account deletion.</h1><p class="muted">Use this form or the app's Account screen to ask NISHA to remove your personal account and practice data.</p></div></div>
    <?php if ($error): ?><div class="setup-notice" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($receipt): ?><section class="dashboard-panel" role="status"><h2>Your request was received.</h2><p><?= e($receipt['message']) ?></p><p>The owner should process requests within 30 days. Payment, accounting, dispute, moderation and legally required records may be retained with a pseudonymous account reference.</p><a class="btn btn-primary" href="index.php">Return to NISHA →</a></section>
    <?php else: ?><section class="dashboard-panel"><h2>Confirm your request</h2><p>This signs you out across devices. Completion removes private practice and AI history, closes your login and anonymises retained account references. Financial and legally required records are retained as described in our privacy information. The owner processes confirmed requests; submitting this form does not claim removal has already completed.</p>
        <form method="post" class="auth-form"><?= csrf_field() ?><label for="deletion-password">Your current password</label><input id="deletion-password" name="password" type="password" autocomplete="current-password" required maxlength="72"><label for="deletion-reason">Reason (optional)</label><input id="deletion-reason" name="reason" maxlength="500"><label><input type="checkbox" name="confirmation" value="1" required> I want to request deletion of this account and understand the retained-record information.</label><button class="btn btn-primary" type="submit">Submit deletion request</button></form><a class="text-link" href="dashboard.php">Return to my dashboard →</a></section><?php endif; ?>
    <p><a href="mobile-privacy.php">Read mobile privacy information →</a></p>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
