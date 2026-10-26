<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'NISHA on your phone';
$pageDescription = 'Practise IELTS and PTE on your phone with NISHA for Android and iOS.';
$activePage = 'apps';
$extraStyles = ['assets/css/account.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container account-page">
    <div class="account-intro"><div><p class="eyebrow">YOUR PRACTICE. IN YOUR POCKET.</p><h1>A little progress, wherever you are.</h1><p class="muted">NISHA brings IELTS and PTE preparation to your phone, with an offline practice pack and online access to your account.</p></div></div>
    <section class="dashboard-grid" aria-label="NISHA mobile apps">
        <article class="dashboard-panel"><p class="eyebrow">ANDROID · TESTING BUILD</p><h2>Try NISHA for Android.</h2><p>Includes 80 generated offline practice questions across four courses. Connect to NISHA for current practice, timed mocks, coaching lessons and your saved results once the mobile backend is deployed.</p><a class="btn btn-primary" href="https://github.com/nishameetmewada/nisha/raw/refs/heads/main/mobile/releases/nisha-android-debug.apk">Download Android test APK ↓</a><p class="muted">This is a development-signed testing build for Android 7 or newer with an updated Android System WebView. A Google Play release needs the owner's production signing and store review.</p></article>
        <article class="dashboard-panel"><p class="eyebrow">IOS · PROJECT PREPARED</p><h2>Made for iPhone, too.</h2><p>The shared app and iOS project are included. An installable iPhone build must be compiled and signed on a Mac with the owner's Apple Developer account.</p><p class="muted">TestFlight and App Store download links will be added after signing and review. There is no published iPhone download yet.</p></article>
    </section>
    <section class="dashboard-panel"><h2>Useful practice, on a smaller screen.</h2><p>Choose reading, listening, writing or speaking. Use clear touch controls, a larger text layout, light or dark mode, on-device listening voices and optional speaking recordings.</p><p>Offline questions are generated learning variations, not teacher-certified material. Offline practice points stay on the device. AI feedback, account saving, coaching videos and full mocks need an internet connection. Recordings are sent for AI review only when you choose to upload them.</p><p>Store builds use existing NISHA account access. Store purchases require the owner's app-store billing setup; this test app does not take card payments.</p><a class="text-link" href="mobile-privacy.php">Mobile privacy and account deletion →</a></section>
    <section class="dashboard-panel"><h2>Start in your phone's browser today.</h2><p>The NISHA website also works on mobile. Keep practising while the store releases are prepared.</p><a class="btn btn-outline" href="practice.php">Open mobile practice →</a></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
