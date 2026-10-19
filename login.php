<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$ready = database_ready();
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!$ready) {
        $error = 'Sign-in is temporarily unavailable. Please try again later. Free practice is available now.';
    } elseif (time() < (int) ($_SESSION['login_blocked_until'] ?? 0)) {
        $error = 'Please wait a moment before trying to sign in again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $password === '' || strlen($password) > 72) {
        $error = 'Enter your email address and password.';
    } else {
        try {
            $statement = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
            $statement->execute([$email]);
            $account = $statement->fetch();
            // Check a valid dummy hash as well, so missing accounts follow the same password check.
            $hash = $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
            $valid = password_verify($password, $hash);
            if ($account && $valid) {
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    $update->execute([password_hash($password, PASSWORD_DEFAULT), $account['id']]);
                }
                sign_in((int) $account['id']);
                redirect('dashboard.php');
            }
            $_SESSION['login_failures'] = (int) ($_SESSION['login_failures'] ?? 0) + 1;
            if ($_SESSION['login_failures'] >= 5) {
                $_SESSION['login_blocked_until'] = time() + 60;
                $_SESSION['login_failures'] = 0;
            }
            $error = 'The email address or password is incorrect.';
        } catch (Throwable $exception) {
            $error = 'We could not sign you in right now. Please try again shortly.';
        }
    }
}

$pageTitle = 'Sign in';
$pageDescription = 'Sign in to NISHA to save your IELTS and PTE practice results and keep your preparation moving.';
$activePage = 'login';
$extraStyles = ['assets/css/account.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container auth-shell">
    <section class="auth-story" aria-labelledby="auth-heading">
        <span class="eyebrow">A little practice. A bigger future.</span>
        <h1 id="auth-heading">Your next chapter<br>starts here<span aria-hidden="true">.</span></h1>
        <p class="auth-lead">Welcome back to your space to learn, grow, and get a little closer to your goals.</p>
        <ul class="auth-points">
            <li>Practice IELTS and PTE at your own pace</li>
            <li>Get instant feedback on objective questions</li>
            <li>Keep your results together in one place</li>
        </ul>
        <div class="auth-note"><span class="auth-badge" aria-hidden="true">N</span><p>Small steps, every day.<br><strong>That’s how confidence grows.</strong></p></div>
    </section>
    <section class="auth-card" aria-labelledby="login-heading">
        <span class="eyebrow">WELCOME BACK</span>
        <h2 id="login-heading">Sign in to NISHA</h2>
        <p class="form-intro">Enter your email address and password to pick up where you left off.</p>
        <?php if (!$ready): ?>
            <div class="setup-notice" role="status">Sign-in is temporarily unavailable. Please try again later, or <a href="practice.php">explore free practice</a>.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="login.php">
            <?= csrf_field() ?>
            <div class="form-field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" autocomplete="email" placeholder="you@example.com" value="<?= e($email) ?>" maxlength="254" required>
            </div>
            <div class="form-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
            </div>
            <button class="btn btn-primary form-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">New to NISHA? <a href="register.php">Create an account</a></p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
