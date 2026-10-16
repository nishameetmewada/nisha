<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$summary = ['sessions' => 0, 'correct' => 0, 'questions' => 0, 'seconds' => 0];
$attempts = [];
$resultsUnavailable = false;
try {
    $statement = db()->prepare('SELECT COUNT(*) AS sessions, COALESCE(SUM(score), 0) AS correct, COALESCE(SUM(total), 0) AS questions, COALESCE(SUM(duration_seconds), 0) AS seconds FROM attempts WHERE user_id = ?');
    $statement->execute([$user['id']]);
    $summary = $statement->fetch();
    $statement = db()->prepare('SELECT exam, skill, score, total, duration_seconds, created_at FROM attempts WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10');
    $statement->execute([$user['id']]);
    $attempts = $statement->fetchAll();
} catch (Throwable $exception) {
    $resultsUnavailable = true;
}
$accuracy = (int) $summary['questions'] > 0
    ? (int) round(100 * (int) $summary['correct'] / (int) $summary['questions'])
    : null;
$firstName = preg_split('/\s+/u', trim($user['name']))[0] ?? $user['name'];
$pageTitle = 'Your dashboard';
$pageDescription = 'Your IELTS and PTE practice progress, recent results, and next steps.';
$activePage = 'dashboard';
$extraStyles = ['assets/css/account.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container account-page">
    <div class="account-intro">
        <div>
            <span class="eyebrow">YOUR LEARNING SPACE</span>
            <h1>Keep growing, <?= e($firstName) ?><span aria-hidden="true">.</span></h1>
            <p class="muted">Every practice is another step toward your next chapter.</p>
        </div>
        <div class="account-actions">
            <a class="btn btn-primary" href="practice.php">Start practicing <span aria-hidden="true">→</span></a>
            <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="btn btn-outline">Sign out</button></form>
        </div>
    </div>

    <?php if ($resultsUnavailable): ?><div class="setup-notice" role="status">Your saved results are temporarily unavailable. You can still keep practicing.</div><?php endif; ?>

    <section class="dashboard-grid" aria-label="Your practice at a glance">
        <article class="stat-card">
            <span class="stat-label">Practice sessions</span>
            <strong class="stat-value"><?= $resultsUnavailable ? '—' : (int) $summary['sessions'] ?></strong>
            <span class="stat-helper">Each one is a step forward</span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Overall accuracy</span>
            <strong class="stat-value"><?= $accuracy === null || $resultsUnavailable ? '—' : $accuracy . '%' ?></strong>
            <span class="stat-helper">Across saved objective questions</span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Time invested</span>
            <strong class="stat-value"><?= $resultsUnavailable ? '—' : (int) ceil((int) $summary['seconds'] / 60) ?><small><?= $resultsUnavailable ? '' : ' min' ?></small></strong>
            <span class="stat-helper">Making time for your future</span>
        </article>
    </section>

    <div class="dashboard-main">
        <section class="dashboard-panel" aria-labelledby="recent-heading">
            <div class="panel-heading"><h2 id="recent-heading">Your recent practice</h2><span class="muted">Last 10 sessions</span></div>
            <?php if ($attempts === []): ?>
                <div class="empty-state">
                    <span class="eyebrow">A FRESH START</span>
                    <h3><?= $resultsUnavailable ? 'Let’s keep your momentum.' : 'Your journey starts with one question.' ?></h3>
                    <p><?= $resultsUnavailable ? 'Your history will be back soon. Explore a practice activity in the meantime.' : 'Complete a reading or listening activity and save your result. You’ll see your progress here.' ?></p>
                    <a class="btn btn-primary" href="practice.php">Find my first practice <span aria-hidden="true">→</span></a>
                </div>
            <?php else: ?>
                <div class="attempts-table-wrap">
                    <table class="attempts-table">
                        <caption class="sr-only">Your ten most recent saved practice results</caption>
                        <thead><tr><th scope="col">Practice</th><th scope="col">Result</th><th scope="col">Time</th><th scope="col">Date (UTC)</th></tr></thead>
                        <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <tr>
                                <td><span class="exam-tag <?= strtolower($attempt['exam']) ?>"><?= e($attempt['exam']) ?></span><br><?= e($attempt['skill']) ?></td>
                                <td><strong><?= (int) $attempt['score'] ?>/<?= (int) $attempt['total'] ?></strong> <span class="muted">(<?= (int) round(100 * (int) $attempt['score'] / max(1, (int) $attempt['total'])) ?>%)</span></td>
                                <td><?= (int) floor((int) $attempt['duration_seconds'] / 60) ?>:<?= str_pad((string) ((int) $attempt['duration_seconds'] % 60), 2, '0', STR_PAD_LEFT) ?></td>
                                <td><time datetime="<?= e(str_replace(' ', 'T', $attempt['created_at']) . 'Z') ?>"><?= e((new DateTimeImmutable($attempt['created_at'], new DateTimeZone('UTC')))->format('d M Y')) ?></time></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <aside class="dashboard-panel" aria-labelledby="next-heading">
            <div class="panel-heading"><h2 id="next-heading">What’s your next step?</h2></div>
            <div class="practice-paths">
                <a href="practice.php?exam=ielts" class="practice-path-card"><span class="exam-tag ielts">IELTS</span><h3>Build your confidence.</h3><p>Explore reading, listening, writing, and speaking activities.</p><strong>Practice IELTS <span aria-hidden="true">→</span></strong></a>
                <a href="practice.php?exam=pte" class="practice-path-card"><span class="exam-tag pte">PTE</span><h3>Find your rhythm.</h3><p>Get familiar with English skills for your PTE preparation.</p><strong>Practice PTE <span aria-hidden="true">→</span></strong></a>
            </div>
        </aside>
    </div>
    <div class="account-banner"><p><strong>Progress has its own pace.</strong> These short practice results help you reflect on what to study next. They are not official IELTS band scores or PTE scores.</p></div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
