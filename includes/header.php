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
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="preload" href="assets/fonts/manrope-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/fonts/dm-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php foreach ($extraStyles ?? [] as $stylesheet): ?>
    <link rel="stylesheet" href="<?= e($stylesheet) ?>">
    <?php endforeach; ?>
    <script src="assets/js/site.js" defer></script>
</head>
<body class="page-<?= e($activePage) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container nav-wrap">
        <a href="index.php" class="brand" aria-label="NISHA home"><svg viewBox="0 0 36 36" width="34" height="34" fill="none" aria-hidden="true"><path d="M18 30V16M18 21C7 21 5 14 6 6c9 0 14 5 12 15ZM18 16c0-7 5-11 12-10 1 7-4 13-12 13" fill="currentColor"/><path d="m10 11 8 10M25 11l-7 8" stroke="#f8f7f2" stroke-width="1.5"/></svg><span>NISHA<span class="brand-dot">.</span></span></a>
        <nav class="main-nav" id="main-nav" aria-label="Main navigation">
            <a href="index.php" <?= $activePage === 'home' ? 'aria-current="page"' : '' ?>>Home</a>
            <a href="exams.php" <?= $activePage === 'exams' ? 'aria-current="page"' : '' ?>>Full mock exams</a>
            <a href="practice.php" <?= $activePage === 'practice' ? 'aria-current="page"' : '' ?>>Practice library</a>
            <a href="index.php#how-it-works">How it works</a>
        </nav>
        <div class="nav-actions">
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
