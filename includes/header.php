<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ui.php';
$pageTitle = $pageTitle ?? 'Your next chapter starts here';
$pageDescription = $pageDescription ?? 'Find your confidence with NISHA. Thoughtful IELTS and PTE practice, interactive exercises, and a little progress every day.';
$activePage = $activePage ?? 'home';
$student = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d523d">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> — NISHA</title>
    <script src="<?= e(nisha_asset_url('assets/js/theme-init.js')) ?>"></script>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="preload" href="assets/fonts/manrope-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/fonts/dm-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(nisha_asset_url('assets/css/style.css')) ?>">
    <?php foreach ($extraStyles ?? [] as $stylesheet): ?>
    <link rel="stylesheet" href="<?= e(nisha_asset_url($stylesheet)) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= e(nisha_asset_url('assets/css/theme.css')) ?>">
    <script src="<?= e(nisha_asset_url('assets/js/theme.js')) ?>" defer></script>
    <script src="<?= e(nisha_asset_url('assets/js/site.js')) ?>" defer></script>
</head>
<body class="page-<?= e($activePage) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container nav-wrap">
        <a href="index.php" class="brand" aria-label="NISHA home"><svg viewBox="0 0 36 36" width="34" height="34" fill="none" aria-hidden="true"><path d="M18 30V16M18 21C7 21 5 14 6 6c9 0 14 5 12 15ZM18 16c0-7 5-11 12-10 1 7-4 13-12 13" fill="currentColor"/><path d="m10 11 8 10M25 11l-7 8" stroke="#f8f7f2" stroke-width="1.5"/></svg><span>NISHA<span class="brand-dot">.</span></span></a>
        <nav class="main-nav" id="main-nav" aria-label="Main navigation">
            <a href="index.php" <?= $activePage === 'home' ? 'aria-current="page"' : '' ?>>Home</a>
            <a href="exams.php" <?= $activePage === 'exams' ? 'aria-current="page"' : '' ?>>Mock tests</a>
            <a href="practice.php" <?= $activePage === 'practice' ? 'aria-current="page"' : '' ?>>Practice</a>
            <a href="coaching.php" <?= $activePage === 'coaching' ? 'aria-current="page"' : '' ?>>Coaching</a>
            <a href="pricing.php" <?= $activePage === 'pricing' ? 'aria-current="page"' : '' ?>>Pricing</a>
            <a class="nav-mobile-account" href="<?= $student ? 'dashboard.php' : 'register.php' ?>"><?= $student ? 'My dashboard' : 'Create free account' ?></a>
        </nav>
        <div class="nav-actions">
            <button class="theme-toggle" type="button" role="switch" aria-checked="false" aria-label="Dark mode" title="Switch to dark mode" hidden>
                <svg class="theme-icon-sun" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                <svg class="theme-icon-moon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/></svg>
                <span class="theme-toggle-label" aria-hidden="true">Light</span>
            </button>
            <?php if ($student): ?>
                <a class="login-link" href="dashboard.php">My dashboard</a>
                <a class="btn btn-primary btn-small" href="practice.php">Let’s practice <?= icon('arrow', '', 17) ?></a>
            <?php else: ?>
                <a class="login-link" href="login.php">Log in</a>
                <a class="btn btn-primary btn-small" href="register.php">Get started <?= icon('arrow', '', 17) ?></a>
            <?php endif; ?>
        </div>
        <button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="main-nav"><?= icon('menu') ?></button>
    </div>
</header>
