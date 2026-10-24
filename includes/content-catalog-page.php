<?php
declare(strict_types=1);
require_once __DIR__ . '/generated-library.php';
$kind = $contentKind === 'mocks' ? 'mocks' : 'questions';
$course = is_string($_GET['course'] ?? null) && isset(nisha_generated_courses()[$_GET['course']]) ? $_GET['course'] : 'ielts-academic';
$page = is_string($_GET['page'] ?? null) && ctype_digit($_GET['page']) ? max(1, min(100000,(int)$_GET['page'])) : 1;
$stats = nisha_generated_stats(); $catalog = nisha_generated_page($kind,$course,$page);
$pageTitle = $kind === 'mocks' ? 'Teacher-reviewed mock library' : 'Teacher-reviewed question bank';
$activePage = $kind === 'mocks' ? 'mocks' : 'questions'; $extraStyles = ['assets/css/content.css'];
require __DIR__ . '/header.php';
?>
<main id="main" class="container content-page">
  <div class="content-heading"><p class="eyebrow">CHECKED BY TEACHERS. BUILT FOR PRACTICE.</p><h1><?= e($pageTitle) ?></h1><p>Only content certified by an approved teacher appears in this library. Teachers check the source, question and answer before publishing.</p></div>
  <div class="content-stats"><div class="content-stat"><strong><?= number_format($stats['question_count']) ?></strong><span>published reviewed questions</span></div><div class="content-stat"><strong><?= number_format($stats['mock_count']) ?></strong><span>published reviewed mock versions</span></div></div>
  <p class="content-small">Our targets are 30,000 teacher-reviewed questions and 3,000 reviewed mock versions. These are expansion goals, not content currently available. Assembled mocks reuse the reviewed question bank.</p>
  <div class="content-actions"><a class="text-link" href="<?= $kind === 'mocks' ? 'question-bank.php' : 'mock-library.php' ?>"><?= $kind === 'mocks' ? 'Browse reviewed questions' : 'Browse reviewed mocks' ?> →</a><a class="text-link" href="<?= $kind === 'mocks' ? 'generated-mocks.php' : 'generated-practice.php' ?>">Explore generated <?= $kind === 'mocks' ? 'mocks' : 'questions' ?> (not teacher reviewed) →</a><a class="text-link" href="practice.php">Explore starter practice →</a><a class="text-link" href="teacher-portal.php">Help build the reviewed library →</a></div>
  <form class="content-filter" method="get"><label for="content-course">Choose your course<select id="content-course" name="course"><?php foreach(nisha_generated_courses() as $id=>$definition): ?><option value="<?= e($id) ?>" <?= $course===$id?'selected':'' ?>><?= e($definition['name']) ?></option><?php endforeach; ?></select></label><button class="btn btn-outline" type="submit">Show course</button></form>
  <?php if (!$catalog['items']): ?><section class="content-empty" aria-labelledby="content-empty-title"><h2 id="content-empty-title">The reviewed collection is being prepared.</h2><p>There are no published teacher-reviewed <?= $kind === 'mocks' ? 'mock versions' : 'questions' ?> for this course yet. Drafts remain private until teachers finish their checks.</p><a class="btn btn-primary" href="practice.php">Use starter practice →</a></section><?php else: ?><div class="content-grid"><?php foreach($catalog['items'] as $item): ?><article class="content-card"><p class="content-badge">Teacher reviewed · <?= e($kind === 'mocks' ? 'Assembled mock' : ucfirst($item['skill'])) ?></p><h2><?= e($item['name']) ?></h2><p><?= (int)$item['item_count'] ?> tasks · <?= (int)ceil($item['duration_seconds']/60) ?> <?= $kind==='mocks'?'minutes':'suggested minutes' ?></p><p class="content-small">Reviewed <?= e(substr($item['reviewed_at'],0,10)) ?></p><a class="btn btn-outline" href="<?= e($item['launch_url']) ?>"><?= $kind==='mocks'?'Open mock':'Start practice' ?> →</a></article><?php endforeach; ?></div><?php endif; ?>
  <?php if ($catalog['pages']>1): ?><nav class="content-pagination" aria-label="Library pages"><?php if($catalog['page']>1): ?><a href="?<?= e(http_build_query(['course'=>$course,'page'=>$catalog['page']-1])) ?>">← Previous</a><?php endif; ?><span>Page <?= (int)$catalog['page'] ?> of <?= (int)$catalog['pages'] ?></span><?php if($catalog['page']<$catalog['pages']): ?><a href="?<?= e(http_build_query(['course'=>$course,'page'=>$catalog['page']+1])) ?>">Next →</a><?php endif; ?></nav><?php endif; ?>
  <p class="content-small"><?= e(nisha_generated_disclosure()) ?> The existing starter library is separate and is not labelled teacher-reviewed.</p>
</main>
<?php require __DIR__ . '/footer.php'; ?>
