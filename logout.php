<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf()) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        redirect('index.php');
    }
    $error = 'Your session expired. Please try again.';
}

if (current_user() === null) {
    redirect('login.php');
}
$pageTitle = 'Sign out';
$pageDescription = 'Sign out of your NISHA account.';
$extraStyles = ['assets/css/account.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container section">
    <section class="auth-card" style="max-width: 480px; margin: 40px auto;">
        <span class="eyebrow">SEE YOU SOON</span>
        <h1>Ready to sign out?</h1>
        <p class="form-intro">Your saved practice results will be here when you return.</p>
        <?php if ($error !== ''): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="btn btn-primary form-submit" type="submit">Sign out</button>
        </form>
        <p class="auth-switch"><a href="dashboard.php">Back to my dashboard</a></p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
