<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$ready = database_ready();
$errors = [];
$name = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
    $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    $passwordLength = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);

    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$ready) {
        $errors[] = 'Student accounts are being set up. Free practice is available now.';
    } else {
        if ($nameLength < 2 || $nameLength > 100 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            $errors[] = 'Enter your name using 2–100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($passwordLength < 8) {
            $errors[] = 'Choose a password with at least 8 characters.';
        } elseif (strlen($password) > 72 || str_contains($password, "\0")) {
            $errors[] = 'Choose a shorter password without null characters (maximum 72 bytes).';
        }
        if ($password !== $confirmation) {
            $errors[] = 'The two passwords do not match.';
        }
        if ($errors === []) {
            try {
                $statement = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
                $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                sign_in((int) db()->lastInsertId());
                redirect('dashboard.php');
            } catch (PDOException $exception) {
                $errors[] = $exception->getCode() === '23000'
                    ? 'An account with this email already exists. Please sign in.'
                    : 'We could not create your account right now. Please try again shortly.';
            } catch (Throwable $exception) {
                $errors[] = 'We could not create your account right now. Please try again shortly.';
            }
        }
    }
}

$pageTitle = 'Create your account';
$pageDescription = 'Create a free NISHA account and build confidence with IELTS and PTE practice.';
$activePage = 'register';
$extraStyles = ['assets/css/account.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container auth-shell">
    <section class="auth-story" aria-labelledby="auth-heading">
        <span class="eyebrow">Your ambition has a home.</span>
        <h1 id="auth-heading">Big dreams.<br>One first step<span aria-hidden="true">.</span></h1>
        <p class="auth-lead">A new university. A new country. A new possibility. Wherever you’re heading, let’s prepare for it together.</p>
        <ul class="auth-points">
            <li>Free IELTS and PTE practice activities</li>
            <li>Your own dashboard and practice history</li>
            <li>Space to learn at a pace that works for you</li>
        </ul>
        <div class="auth-note"><span class="auth-badge" aria-hidden="true">N</span><p>Your journey, your pace.<br><strong>We’re here for the next step.</strong></p></div>
    </section>
    <section class="auth-card" aria-labelledby="register-heading">
        <span class="eyebrow">LET’S GET STARTED</span>
        <h2 id="register-heading">Make room for possibility.</h2>
        <p class="form-intro">Create your free account. Start building confidence.</p>
        <?php if (!$ready): ?>
            <div class="setup-notice" role="status">Student accounts are being set up. You can <a href="practice.php">explore free practice</a> while we get ready.</div>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <div class="form-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" action="register.php">
            <?= csrf_field() ?>
            <div class="form-field">
                <label for="name">Your name</label>
                <input type="text" id="name" name="name" autocomplete="name" placeholder="Your full name" value="<?= e($name) ?>" minlength="2" maxlength="100" required <?= !$ready ? 'disabled' : '' ?>>
            </div>
            <div class="form-field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" autocomplete="email" placeholder="you@example.com" value="<?= e($email) ?>" maxlength="254" required <?= !$ready ? 'disabled' : '' ?>>
            </div>
            <div class="form-field">
                <label for="password">Create a password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8" aria-describedby="password-hint" required <?= !$ready ? 'disabled' : '' ?>>
                <span class="form-hint" id="password-hint">At least 8 characters. Make it unique to your NISHA account.</span>
            </div>
            <div class="form-field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" placeholder="Enter your password again" minlength="8" required <?= !$ready ? 'disabled' : '' ?>>
            </div>
            <button class="btn btn-primary form-submit" type="submit" <?= !$ready ? 'disabled' : '' ?>>Create my account <span aria-hidden="true">→</span></button>
        </form>
        <p class="form-password-note">Your practice history is private to your account.</p>
        <p class="auth-switch">Already part of NISHA? <a href="login.php">Sign in</a></p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
