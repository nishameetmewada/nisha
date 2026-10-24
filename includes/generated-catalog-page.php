<?php
declare(strict_types=1);
require_once __DIR__ . '/generated-public-library.php';
$kind = $generatedKind === 'mocks' ? 'mocks' : 'questions';
$course = is_string($_GET['course'] ?? null) && isset(nisha_generated_courses()[$_GET['course']]) ? $_GET['course'] : 'ielts-academic';
$page = is_string($_GET['page'] ?? null) && ctype_digit($_GET['page']) ? max(1, min(100000, (int) $_GET['page'])) : 1;
$filters = nisha_generated_public_filters($course);
$skill = $kind === 'questions' && is_string($_GET['skill'] ?? null) && isset($filters['skills'][$_GET['skill']]) ? $_GET['skill'] : 'all';
$typeIds = array_column($filters['task_types'], 'id');
$taskType = $kind === 'questions' && is_string($_GET['type'] ?? null) && in_array($_GET['type'], $typeIds, true) ? $_GET['type'] : 'all';
$stats = nisha_generated_public_stats();
$catalog = nisha_generated_public_page($kind, $course, $page, 24, $skill, $taskType);
$pageTitle = $kind === 'mocks' ? 'Generated mock library' : 'Generated practice questions';
$pageDescription = 'Explore clearly labelled generated IELTS and PTE practice variations at NISHA. Separate from the teacher-reviewed collection.';
$activePage = $kind === 'mocks' ? 'exams' : 'practice';
$extraStyles = ['assets/css/content.css'];
$paginationQuery = ['course' => $course];
if ($skill !== 'all') $paginationQuery['skill'] = $skill;
if ($taskType !== 'all') $paginationQuery['type'] = $taskType;
require __DIR__ . '/header.php';
?>
<main id="main" class="container content-page generated-page">
    <div class="content-heading"><p class="eyebrow">MORE WAYS TO PRACTISE</p><h1><?= e($pageTitle) ?></h1><p>Choose your course and build your skills with generated practice. These variations are available now and have not been certified by a teacher.</p></div>
    <div class="content-stats"><div class="content-stat"><strong><?= number_format((int) $stats['question_count']) ?></strong><span>generated question variants</span></div><div class="content-stat"><strong><?= number_format((int) $stats['mock_count']) ?></strong><span>assembled mock variants</span></div></div>
    <aside class="content-message generated-disclosure" aria-labelledby="generated-disclosure-title"><h2 id="generated-disclosure-title">Generated · not teacher reviewed</h2><p><?= e(nisha_generated_public_disclosure()) ?></p><p><?= $kind === 'mocks' ? 'Your first completed full mock is free with an account. Further generated and starter full mocks use the same access pass.' : 'Generated question practice is free. Check objective answers and explanations as you go; writing and speaking offer optional AI coaching when available.' ?></p></aside>
    <nav class="content-actions" aria-label="Explore the libraries"><a class="text-link" href="<?= $kind === 'mocks' ? 'generated-practice.php' : 'generated-mocks.php' ?>?<?= e(http_build_query(['course' => $course])) ?>"><?= $kind === 'mocks' ? 'Generated question practice' : 'Generated full mocks' ?> →</a><a class="text-link" href="<?= $kind === 'mocks' ? 'mock-library.php' : 'question-bank.php' ?>?<?= e(http_build_query(['course' => $course])) ?>">Teacher-reviewed <?= $kind === 'mocks' ? 'mocks' : 'questions' ?> →</a><a class="text-link" href="<?= $kind === 'mocks' ? 'exams.php' : 'practice.php' ?>">Starter <?= $kind === 'mocks' ? 'mocks' : 'practice' ?> →</a></nav>
    <form class="content-filter generated-filter" method="get">
        <label for="generated-course">Choose your course<select id="generated-course" name="course"><?php foreach (nisha_generated_courses() as $id => $definition): ?><option value="<?= e($id) ?>" <?= $course === $id ? 'selected' : '' ?>><?= e($definition['name']) ?></option><?php endforeach; ?></select></label>
        <?php if ($kind === 'questions'): ?>
        <label for="generated-skill">Skill<select id="generated-skill" name="skill"><option value="all">All skills</option><?php foreach ($filters['skills'] as $id => $name): ?><?php if ($id === 'all') continue; ?><option value="<?= e($id) ?>" <?= $skill === $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
        <label for="generated-type">Question type<select id="generated-type" name="type"><option value="all">All question types</option><?php foreach ($filters['task_types'] as $definition): ?><option value="<?= e($definition['id']) ?>" <?= $taskType === $definition['id'] ? 'selected' : '' ?>><?= e($definition['name']) ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Show <?= $kind === 'mocks' ? 'mocks' : 'questions' ?> →</button>
    </form>
    <div class="generated-results-heading"><h2><?= e(nisha_generated_courses()[$course]['name']) ?></h2><p><?= number_format((int) $catalog['total']) ?> <?= $kind === 'mocks' ? 'assembled mock variants' : 'matching question variants' ?></p></div>
    <?php if (!$catalog['items']): ?>
    <section class="content-empty" aria-labelledby="generated-empty-title"><h2 id="generated-empty-title">Try a different focus.</h2><p>No generated questions match this combination of skill and question type.</p><a class="btn btn-outline" href="generated-practice.php?<?= e(http_build_query(['course' => $course])) ?>">Clear filters →</a></section>
    <?php else: ?>
    <div class="content-grid"><?php foreach ($catalog['items'] as $item): ?>
        <article class="content-card generated-card"><p class="content-badge">Generated · <?= e($kind === 'mocks' ? 'Assembled full mock' : ucfirst($item['skill'])) ?></p><h3><?= e($item['name']) ?></h3><?php if ($kind === 'questions'): ?><p class="generated-task-type"><?= e($item['task_type']) ?></p><?php endif; ?><p><?= (int) $item['item_count'] ?> <?= (int) $item['item_count'] === 1 ? 'task' : 'tasks' ?> · <?= (int) ceil($item['duration_seconds'] / 60) ?> <?= $kind === 'mocks' ? 'minutes' : 'suggested minutes' ?></p><p class="content-small">Not teacher reviewed<?= $kind === 'mocks' ? ' · Reuses generated question variants' : '' ?></p><a class="btn btn-outline" href="<?= e($item['launch_url']) ?>"><?= $kind === 'mocks' ? 'Open generated mock' : 'Start generated practice' ?> →</a></article>
    <?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($catalog['pages'] > 1): ?><nav class="content-pagination" aria-label="Generated library pages"><?php if ($catalog['page'] > 1): ?><a href="?<?= e(http_build_query($paginationQuery + ['page' => $catalog['page'] - 1])) ?>">← Previous</a><?php endif; ?><span>Page <?= (int) $catalog['page'] ?> of <?= (int) $catalog['pages'] ?></span><?php if ($catalog['page'] < $catalog['pages']): ?><a href="?<?= e(http_build_query($paginationQuery + ['page' => $catalog['page'] + 1])) ?>">Next →</a><?php endif; ?></nav><?php endif; ?>
    <p class="content-small">These are independent NISHA learning materials. Objective practice points and AI feedback are estimates for learning, not official IELTS bands or PTE scores. Listening uses browser speech.</p>
</main>
<?php require __DIR__ . '/footer.php'; ?>
