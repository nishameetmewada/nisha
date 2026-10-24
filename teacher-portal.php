<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/teachers.php';
$user = require_login();
$error = ''; $success = ''; $unavailable = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) $error = 'Your session expired. Refresh the page and try again.';
    else try {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        if ($action === 'apply') { nisha_teacher_apply($user, $_POST); $success = 'Your teacher application is ready for the site owner to review.'; }
        elseif ($action === 'submit_video') { nisha_teacher_submit_video($user, $_POST); $success = 'Your coaching video was submitted for review. It will appear in the library after approval.'; }
        else throw new InvalidArgumentException('Choose an available teacher action.');
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { $error = 'The teacher portal is temporarily unavailable. Your changes were not confirmed. Please try again later.'; }
}
$data = ['profile' => null, 'videos' => [], 'rewards' => [], 'totals' => ['allocated' => 0, 'paid' => 0], 'settings' => nisha_teacher_settings()];
try { $data = nisha_teacher_page_data($user); } catch (Throwable $exception) { $unavailable = true; }
$profile = $data['profile']; $settings = $data['settings'];
$approved = is_array($profile) && $profile['status'] === 'approved';
$editing = null;
$editId = is_string($_GET['edit'] ?? null) && ctype_digit($_GET['edit']) ? (int) $_GET['edit'] : 0;
foreach ($data['videos'] as $video) if ((int) $video['id'] === $editId) $editing = $video;
$posted = static function (string $key, string $fallback = ''): string { return is_string($_POST[$key] ?? null) ? $_POST[$key] : $fallback; };
$pageTitle = 'Teacher portal'; $activePage = 'coaching'; $extraStyles = ['assets/css/teachers.css'];
include __DIR__ . '/includes/header.php';
?>
<main class="container teacher-page" id="main">
    <div class="teacher-intro"><div><p class="eyebrow">TEACH. SUPPORT. HELP STUDENTS GROW.</p><h1>Your coaching space.</h1><p>Share IELTS and PTE lessons, follow their qualified views and see rewards allocated from verified business profit.</p><nav class="teacher-nav" aria-label="Coaching pages"><a class="btn btn-outline" href="coaching.php">Coaching library</a><a class="btn btn-outline" href="dashboard.php">My dashboard</a><?php if (nisha_is_admin($user)): ?><a class="btn btn-outline" href="admin.php">Owner controls</a><?php endif; ?></nav></div></div>
    <?php if ($error): ?><div class="teacher-notice is-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="teacher-notice" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($unavailable): ?><div class="teacher-notice" role="status">The teacher portal is temporarily unavailable. Please return later.</div><?php else: ?>
        <?php if (!$approved): ?>
        <div class="teacher-grid"><section class="teacher-panel"><h2><?= $profile ? 'Your teacher application' : 'Apply to teach with NISHA' ?></h2>
            <?php if ($profile): ?><p><span class="teacher-status"><?= e($profile['status']) ?></span></p><?php if (!empty($profile['review_note'])): ?><p class="teacher-copy"><?= e($profile['review_note']) ?></p><?php endif; ?><?php endif; ?>
            <p>The site owner reviews teacher applications before lessons can be submitted and published.</p>
            <form class="teacher-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="apply">
                <label class="teacher-field">Teacher display name<input name="display_name" required minlength="2" maxlength="100" value="<?= e($posted('display_name', (string) ($profile['display_name'] ?? $user['name']))) ?>" autocomplete="name"></label>
                <label class="teacher-field">Your teaching experience<textarea name="bio" rows="6" required minlength="20" maxlength="2000"><?= e($posted('bio', (string) ($profile['bio'] ?? ''))) ?></textarea><small>Describe the exams, skills and teaching experience you can help students with.</small></label>
                <button class="btn btn-primary" type="submit"><?= $profile ? 'Update application' : 'Submit application' ?></button>
            </form>
        </section><aside class="teacher-panel"><h2>How teacher rewards work</h2><p>Approved lessons can earn a share of a positive, owner-verified monthly business profit pool. Qualified views determine each teacher’s share of that pool.</p><p>A view qualifies after <?= (int) $settings['watch_percent'] ?>% of the approved lesson duration has been watched and verified by the server<?= $settings['paid_students_only'] ? ' by a student with active paid access' : ' by a signed-in student' ?>. Self-views and repeat views of the same lesson by the same student in one month do not add rewards.</p><p><?= $settings['enabled'] ? 'The current reward share is ' . e((string) $settings['share_percent']) . '% of verified positive monthly profit.' : 'Teacher rewards are awaiting the owner’s reward terms and activation. Views do not currently promise a payment.' ?></p><p>Income depends on qualified views, business profit and owner approval. No fixed earnings are guaranteed.</p></aside></div>
        <?php else: ?>
        <div class="teacher-stats"><div class="teacher-stat"><span>Your submitted lessons</span><strong><?= count($data['videos']) ?></strong></div><div class="teacher-stat"><span>Allocated rewards</span><strong><?= e(nisha_teacher_money((int) $data['totals']['allocated'], $settings['currency'])) ?></strong></div><div class="teacher-stat"><span>Recorded payouts</span><strong><?= e(nisha_teacher_money((int) $data['totals']['paid'], $settings['currency'])) ?></strong></div></div>
        <?php if (!$settings['enabled']): ?><div class="teacher-notice">Reward allocation is awaiting the owner’s terms and activation. You can submit lessons, but views do not currently promise a payment.</div><?php endif; ?>
        <div class="teacher-grid"><section class="teacher-panel" id="submit-lesson"><h2><?= $editing ? 'Update your lesson' : 'Add a coaching lesson' ?></h2><p>Use a direct MP4 or WebM link from an approved video host. The owner reviews every new or changed lesson before publication.</p>
            <?php if (!$settings['video_hosts']): ?><div class="teacher-notice">Video submissions will be available when the owner adds an approved video host.</div><?php else: ?><p class="teacher-help">Approved hosts: <?= e(implode(', ', $settings['video_hosts'])) ?></p><?php endif; ?>
            <form class="teacher-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="submit_video"><?php if ($editing): ?><input type="hidden" name="video_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
                <label class="teacher-field">Lesson title<input name="title" required minlength="5" maxlength="160" value="<?= e($posted('title', (string) ($editing['title'] ?? ''))) ?>"></label>
                <label class="teacher-field">What students will learn<textarea name="description" required minlength="20" maxlength="4000" rows="4"><?= e($posted('description', (string) ($editing['description'] ?? ''))) ?></textarea></label>
                <label class="teacher-field">Direct video link<input name="video_url" type="url" required maxlength="2000" placeholder="https://your-approved-host.example/lesson.mp4" value="<?= e($posted('video_url', (string) ($editing['video_url'] ?? ''))) ?>"><small>Use an HTTPS link to the video file, rather than a YouTube or webpage link.</small></label>
                <div class="teacher-row"><label class="teacher-field">Course<select name="course_id"><?php foreach (['ielts-academic' => 'IELTS Academic', 'ielts-general' => 'IELTS General Training', 'pte-academic' => 'PTE Academic', 'pte-core' => 'PTE Core'] as $id => $label): ?><option value="<?= e($id) ?>" <?= $posted('course_id', (string) ($editing['course_id'] ?? 'ielts-academic')) === $id ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label class="teacher-field">Skill<select name="skill"><?php foreach (['reading', 'listening', 'writing', 'speaking'] as $skill): ?><option value="<?= e($skill) ?>" <?= $posted('skill', (string) ($editing['skill'] ?? 'reading')) === $skill ? 'selected' : '' ?>><?= e(ucfirst($skill)) ?></option><?php endforeach; ?></select></label></div>
                <label class="teacher-field">Video duration in seconds<input type="number" name="duration_seconds" required min="30" max="7200" step="1" value="<?= e($posted('duration_seconds', isset($editing['duration_seconds']) ? (string) $editing['duration_seconds'] : '')) ?>"><small>Enter the actual complete duration. The owner checks this before approving the lesson.</small></label>
                <button type="submit" class="btn btn-primary" <?= !$settings['video_hosts'] ? 'disabled' : '' ?>>Submit for review</button><?php if ($editing): ?><a href="teacher-portal.php#submit-lesson">Cancel editing</a><?php endif; ?>
            </form>
        </section><section class="teacher-panel"><h2>Your lessons</h2><?php if (!$data['videos']): ?><p>Your first lesson can help a student take their next step. Submit a video to begin.</p><?php else: ?><div class="teacher-list"><?php foreach ($data['videos'] as $video): ?><article class="teacher-list-item"><div class="teacher-list-heading"><h3><?= e($video['title']) ?></h3><span class="teacher-status"><?= e($video['status']) ?></span></div><p class="teacher-help"><?= e($video['course_id']) ?> · <?= e(ucfirst($video['skill'])) ?> · <?= (int) $video['duration_seconds'] ?> seconds</p><p><?= (int) ($video['qualified_views'] ?? 0) ?> qualified views</p><?php if (!empty($video['review_note'])): ?><p class="teacher-copy"><?= e($video['review_note']) ?></p><?php endif; ?><a href="teacher-portal.php?edit=<?= (int) $video['id'] ?>#submit-lesson">Edit and resubmit →</a></article><?php endforeach; ?></div><?php endif; ?></section></div>
        <section class="teacher-panel"><h2>Your reward history</h2><p>Only approved monthly allocations appear here. A payout status records an owner-confirmed transfer; the portal does not transfer funds itself.</p><?php if (!$data['rewards']): ?><p>No rewards have been allocated yet.</p><?php else: ?><div class="teacher-table-wrap"><table class="teacher-table"><caption>Approved rewards and recorded payouts</caption><thead><tr><th scope="col">Month (UTC)</th><th scope="col">Qualified views</th><th scope="col">Reward</th><th scope="col">Status</th><th scope="col">Payout reference</th></tr></thead><tbody><?php foreach ($data['rewards'] as $reward): ?><tr><td><?= e($reward['period']) ?></td><td><?= (int) $reward['qualified_views'] ?></td><td><?= e(nisha_teacher_money((int) $reward['amount_cents'], $reward['currency'])) ?></td><td><?= e($reward['status']) ?></td><td><?= e($reward['payout_reference'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
        <p class="teacher-help">A qualified view requires <?= (int) $settings['watch_percent'] ?>% verified watch time<?= $settings['paid_students_only'] ? ', an active paid student' : ', a signed-in student' ?> and an approved lesson. Your own views do not qualify. The monthly reward pool depends on verified positive business profit, so views alone do not guarantee income.</p>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
