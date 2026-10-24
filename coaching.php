<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/teachers.php';
$user = require_login();
$courses = ['ielts-academic' => 'IELTS Academic', 'ielts-general' => 'IELTS General Training', 'pte-academic' => 'PTE Academic', 'pte-core' => 'PTE Core'];
$skills = ['reading', 'listening', 'writing', 'speaking'];
$course = is_string($_GET['course'] ?? null) && isset($courses[$_GET['course']]) ? $_GET['course'] : '';
$skill = is_string($_GET['skill'] ?? null) && in_array($_GET['skill'], $skills, true) ? $_GET['skill'] : '';
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
if (strlen($search) > 200 || !preg_match('//u', $search) || preg_match('/[\x00-\x1f\x7f]/', $search)) $search = '';
$filtered = $course !== '' || $skill !== '' || $search !== '';
$videos = []; $availableCount = 0; $matchingCount = 0; $unavailable = false; $settings = nisha_teacher_settings();
try {
    $availableCount = nisha_teacher_catalog_count();
    $matchingCount = $filtered ? nisha_teacher_catalog_count($course ?: null, $skill ?: null, $search) : $availableCount;
    $videos = nisha_teacher_catalog($course ?: null, $skill ?: null, $search);
} catch (Throwable $exception) { $unavailable = true; }
$paidAccess = false;
try { $paidAccess = nisha_is_admin($user) || nisha_commerce_has_paid(db(), (int) $user['id']); } catch (Throwable $exception) { /* Keep metadata available during setup. */ }
$pageTitle = 'Coaching videos'; $activePage = 'coaching';
$extraStyles = ['assets/css/teachers.css']; $extraScripts = ['assets/js/coaching.js'];
include __DIR__ . '/includes/header.php';
?>
<main class="container teacher-page" id="main">
    <div class="teacher-intro"><div><p class="eyebrow">A LITTLE GUIDANCE. A CLEARER NEXT STEP.</p><h1>Learn with NISHA teachers.</h1><p>Explore approved IELTS and PTE lessons, then put what you learn into practice.</p><nav class="teacher-nav" aria-label="Coaching pages"><a class="btn btn-primary" href="practice.php">Practise a skill</a><a class="btn btn-outline" href="teacher-portal.php">Teach with NISHA</a><a class="btn btn-outline" href="dashboard.php">My dashboard</a></nav></div></div>
    <section class="teacher-panel" aria-labelledby="coaching-filter-heading"><h2 id="coaching-filter-heading">Find your lesson.</h2><form class="teacher-filters" method="get" action="coaching.php#coaching-results"><label class="teacher-field">Search lessons<input type="search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Topic, lesson or teacher"></label><label class="teacher-field">Course<select name="course"><option value="">All courses</option><?php foreach ($courses as $id => $label): ?><option value="<?= e($id) ?>" <?= $course === $id ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label class="teacher-field">Skill<select name="skill"><option value="">All skills</option><?php foreach ($skills as $item): ?><option value="<?= e($item) ?>" <?= $skill === $item ? 'selected' : '' ?>><?= e(ucfirst($item)) ?></option><?php endforeach; ?></select></label><button class="btn btn-primary" type="submit">Find lessons</button><?php if ($filtered): ?><a class="btn btn-outline" href="coaching.php#coaching-results">Clear filters</a><?php endif; ?></form></section>
    <section id="coaching-results" aria-labelledby="coaching-results-heading">
    <h2 id="coaching-results-heading"><?= $filtered ? 'Your lesson results' : 'Available lessons' ?></h2>
    <?php if ($unavailable): ?><div class="teacher-notice" role="status">The coaching library is temporarily unavailable. Please try again later.</div><?php elseif (!$availableCount): ?><section class="teacher-panel"><h3>No coaching lessons have been published yet.</h3><p>Find lessons searches videos submitted by approved teachers and published by the owner. There are no published lessons in the library yet. You can keep learning with practice questions.</p><?php if (nisha_is_admin($user)): ?><a class="btn btn-outline" href="admin.php#lesson-review">Review teacher lesson submissions →</a><?php endif; ?><a class="btn btn-primary" href="practice.php">Explore practice →</a></section><?php elseif (!$videos): ?><section class="teacher-panel"><h3>No lessons match your search.</h3><p>There <?= $availableCount === 1 ? 'is 1 published lesson' : 'are ' . number_format($availableCount) . ' published lessons' ?> in the library. Try another course, skill or search term, or clear the filters to browse them.</p><a class="btn btn-primary" href="coaching.php#coaching-results">Show all lessons →</a></section><?php else: ?>
    <p class="teacher-help" role="status"><?= number_format($matchingCount) ?> matching <?= $matchingCount === 1 ? 'lesson' : 'lessons' ?><?= $matchingCount > count($videos) ? ' · Showing the latest ' . count($videos) : '' ?><?= $search !== '' ? ' for “' . e($search) . '”' : '' ?>.</p>
    <div class="coaching-grid"><?php foreach ($videos as $video): ?><article class="coaching-card"><p class="eyebrow"><?= e($courses[$video['course_id']] ?? $video['course_id']) ?> · <?= e(ucfirst($video['skill'])) ?></p><h2><?= e($video['title']) ?></h2><div class="coaching-meta"><span>With <?= e($video['teacher_name']) ?></span><span><?= (int) floor((int) $video['duration_seconds'] / 60) ?>:<?= str_pad((string) ((int) $video['duration_seconds'] % 60), 2, '0', STR_PAD_LEFT) ?></span></div><p class="coaching-description teacher-copy"><?= e($video['description']) ?></p><?php if ($paidAccess || (int) $video['teacher_id'] === (int) $user['id']): ?><video class="coaching-video" id="coaching-video-<?= (int) $video['id'] ?>" data-coaching-video-id="<?= (int) $video['id'] ?>" controls playsinline preload="none" aria-label="<?= e($video['title']) ?>"><source src="<?= e($video['video_url']) ?>" type="<?= str_ends_with(strtolower((string) parse_url($video['video_url'], PHP_URL_PATH)), '.webm') ? 'video/webm' : 'video/mp4' ?>">Your browser cannot play this video. Try a supported browser.</video><p class="coaching-progress" id="coaching-progress-<?= (int) $video['id'] ?>" data-coaching-progress="<?= (int) $video['id'] ?>" role="status" aria-live="polite">Press play to begin. Lessons are loaded from the owner-approved video host.</p><?php else: ?><p class="teacher-notice">A paid NISHA access pass unlocks coaching videos.</p><a class="btn btn-primary" href="pricing.php">See the access pass →</a><?php endif; ?></article><?php endforeach; ?></div>
    <p class="teacher-help">NISHA records limited watch progress while a lesson is playing and this tab is visible. Qualified views can support a teacher’s reward allocation when verified business profit is positive. <?= $settings['paid_students_only'] ? 'Reward eligibility requires active paid student access.' : 'Reward eligibility requires a signed-in student.' ?> Seeking, self-views and repeated views do not increase a teacher’s reward count.</p>
    <?php endif; ?>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
