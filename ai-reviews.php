<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/ai-review.php';
$user = require_login();
$reviews = []; $unavailable = false;
try {
    $statement = db()->prepare('SELECT a.id,a.exam_id,a.exam_name,a.task_type,a.response_text,a.review_json,a.created_at,e.id AS result_id FROM ai_reviews a LEFT JOIN exam_runs e ON e.user_id=a.user_id AND e.attempt_id=a.attempt_id AND e.exam_id=a.exam_id WHERE a.user_id=? AND a.status=\'completed\' ORDER BY a.id DESC LIMIT 30');
    $statement->execute([$user['id']]);
    foreach ($statement->fetchAll() as $row) {
        $row['review'] = json_decode($row['review_json'], true, 32, JSON_THROW_ON_ERROR);
        unset($row['review_json']); $reviews[] = $row;
    }
} catch (Throwable $exception) { $unavailable = true; }
$pageTitle = 'Your AI coaching'; $activePage = 'dashboard';
$extraStyles = ['assets/css/account.css', 'assets/css/ai-review.css'];
include __DIR__ . '/includes/header.php';
?>
<main class="container account-page" id="main">
    <div class="account-intro"><div><p class="eyebrow">YOUR PERSONAL FEEDBACK LIBRARY</p><h1>Learn from every response.</h1><p>Your last 30 saved AI reviews. These are practice estimates and may contain mistakes. OpenAI reviews text and transcriptions; speaking delivery is not assessed.</p></div><div class="account-actions"><a class="btn btn-outline" href="dashboard.php">← Dashboard</a><a class="btn btn-primary" href="practice.php">Keep practising →</a></div></div>
    <?php if ($unavailable): ?><div class="setup-notice">AI coaching history is temporarily unavailable. The site owner should check the AI database migration.</div><?php endif; ?>
    <?php if (!$reviews && !$unavailable): ?><div class="empty-state"><h2>Your next response is a place to start.</h2><p>Sign in and request AI coaching from a writing or speaking activity. Completed reviews will appear here.</p><a class="btn btn-primary" href="practice.php">Explore practice →</a></div><?php endif; ?>
    <?php foreach ($reviews as $row): $review = $row['review']; $score = $review['score']; ?>
    <details class="dashboard-panel ai-history-review">
        <summary><span><strong><?= e($row['exam_name']) ?></strong><br><?= e($row['task_type']) ?> · <?= e((new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC')))->format('d M Y')) ?> UTC</span><span><?= $score['value'] === null ? 'Not assessable' : e($score['value'] . ($score['unit'] === '%' ? '%' : '/' . $score['max'])) ?><small> · AI practice estimate</small></span></summary>
        <div class="ai-history-body"><h2><?= e($score['label']) ?></h2><p><?= e($review['summary']) ?></p>
        <h3><?= $review['transcript'] === null ? 'Your submitted response' : 'Transcript reviewed' ?></h3><p class="ai-history-response"><?= e($review['transcript'] ?? $row['response_text']) ?></p>
        <div class="ai-history-criteria"><?php foreach ($review['criteria'] as $criterion): ?><article><h3><?= e($criterion['label']) ?> · <?= $criterion['score'] === null ? 'Not assessed' : e($criterion['score'] . '/' . $criterion['max']) ?></h3><p><?= e($criterion['feedback']) ?></p></article><?php endforeach; ?></div>
        <?php if ($review['strengths']): ?><h3>What is working</h3><ul><?php foreach ($review['strengths'] as $strength): ?><li><?= e($strength) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <h3>Make your next attempt stronger</h3><?php foreach ($review['improvements'] as $improvement): ?><article><strong><?= e($improvement['issue']) ?></strong><?php if ($improvement['evidence'] !== ''): ?><blockquote><?= e($improvement['evidence']) ?></blockquote><?php endif; ?><p><?= e($improvement['suggestion']) ?></p><p><strong>Example:</strong> <?= e($improvement['example']) ?></p></article><?php endforeach; ?>
        <h3>Your next steps</h3><ol><?php foreach ($review['next_steps'] as $step): ?><li><?= e($step) ?></li><?php endforeach; ?></ol>
        <?php if ($review['improved_sample'] !== ''): ?><details><summary>A learning example</summary><p class="ai-history-response"><?= e($review['improved_sample']) ?></p></details><?php endif; ?>
        <ul class="ai-history-limitations"><?php foreach ($review['limitations'] as $limitation): ?><li><?= e($limitation) ?></li><?php endforeach; ?></ul>
        <?php if ($row['result_id']): ?><a class="btn btn-outline" href="exams.php?result=<?= (int) $row['result_id'] ?>">Open the complete result →</a><?php else: ?><a class="btn btn-outline" href="exams.php?exam=<?= e(rawurlencode($row['exam_id'])) ?>&amp;start=1">Return to this practice →</a><?php endif; ?>
        </div>
    </details>
    <?php endforeach; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
