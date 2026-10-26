<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/teachers.php';
require_once __DIR__ . '/includes/mobile-deletion.php';
$owner = require_login(); header('Cache-Control: private, no-store');
$isOwner = nisha_is_admin($owner); if (!$isOwner) http_response_code(403);
$error = ''; $queue = null;
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'open';
$page = is_string($_GET['page'] ?? null) && ctype_digit($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { http_response_code(403); $error = 'Your session expired. Refresh before processing requests.'; }
    else try {
        $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) throw new InvalidArgumentException('Choose a valid account request.');
        nisha_mobile_deletion_action(db(), (int)$owner['id'], $id, is_string($_POST['action'] ?? null) ? $_POST['action'] : '', $_POST);
        redirect('admin-deletion.php?saved=1');
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { $error = 'Deletion processing could not be confirmed. No partial cleanup was committed. Check database setup and try again.'; }
}
if ($isOwner) try { $queue = nisha_mobile_deletion_queue(db(),(int)$owner['id'],$status,$page); } catch (Throwable $exception) { if (!$error) $error = 'The deletion queue needs the latest mobile and account database schema.'; }
$pageTitle = 'Account deletion requests'; $activePage = 'admin'; $extraStyles = ['assets/css/teachers.css','assets/css/admin.css'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container teacher-page admin-page">
<?php if (!$isOwner): ?><h1>Administrator access is required.</h1><p>This account cannot inspect or process deletion requests.</p><a class="btn btn-primary" href="dashboard.php">Return to my dashboard</a>
<?php else: ?><div class="teacher-intro"><div><p class="eyebrow">ACCOUNT PRIVACY</p><h1>Account deletion requests.</h1><p>Process verified requests within 30 days. Completion performs data removal and anonymisation in one transaction while retaining financial and moderation references.</p><nav class="teacher-nav"><a class="btn btn-outline" href="admin.php">Admin dashboard</a><a class="btn btn-outline" href="admin-accounts.php">Account controls</a></nav></div></div>
    <?php if ($error): ?><div class="teacher-notice is-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (($_GET['saved'] ?? '') === '1' && !$error): ?><div class="teacher-notice" role="status">Request processing was saved.</div><?php endif; ?>
    <form method="get" class="teacher-filters"><label class="teacher-field">Request status<select name="status"><?php foreach (['open'=>'Open requests','pending'=>'Pending','in_progress'=>'In progress','completed'=>'Completed','all'=>'All requests'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($queue['status'] ?? 'open') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><button class="btn btn-outline" type="submit">Show requests</button></form>
    <?php if ($queue): ?><p><?= number_format($queue['total']) ?> matching requests.</p><?php foreach ($queue['items'] as $request): ?><section class="teacher-panel"><h2>Account <?= (int)$request['user_id'] ?> · <?= e($request['status']) ?></h2><p class="teacher-copy"><?= e($request['name']) ?> · <?= e($request['email']) ?></p><p>Requested <?= e($request['requested_at']) ?> UTC<?= $request['resolved_at'] ? ' · Completed '.e($request['resolved_at']).' UTC' : '' ?></p><p class="teacher-copy"><?= e($request['reason'] ?: 'No reason supplied.') ?></p><?php if ($request['owner_note']): ?><p class="teacher-copy">Processing note: <?= e($request['owner_note']) ?></p><?php endif; ?>
        <?php if (in_array($request['status'],['pending','in_progress'],true)): ?><form method="post" class="teacher-form"><?= csrf_field() ?><input name="user_id" type="hidden" value="<?= (int)$request['user_id'] ?>"><label class="teacher-field">Your current administrator password<input name="current_password" type="password" autocomplete="current-password" required maxlength="72"></label><label class="teacher-field">Processing note<input name="reason" minlength="5" maxlength="500" required></label><label class="teacher-check"><input name="confirmation" type="checkbox" value="1"><span>Complete removal of private practice and AI history, anonymise this account, hide its teacher material, revoke access and retain only referenced business records. Another owner must process owner accounts.</span></label><div class="teacher-actions"><button class="btn btn-outline" type="submit" name="action" value="begin">Mark in progress</button><button class="btn btn-primary" type="submit" name="action" value="complete">Complete deletion and anonymisation</button></div></form><?php endif; ?></section><?php endforeach; ?>
    <?php if ($queue['pages']>1): ?><nav class="teacher-nav" aria-label="Deletion request pages"><?php if ($queue['page']>1): ?><a href="?<?= e(http_build_query(['status'=>$queue['status'],'page'=>$queue['page']-1])) ?>">← Previous</a><?php endif; ?><span>Page <?= (int)$queue['page'] ?> of <?= (int)$queue['pages'] ?></span><?php if ($queue['page']<$queue['pages']): ?><a href="?<?= e(http_build_query(['status'=>$queue['status'],'page'=>$queue['page']+1])) ?>">Next →</a><?php endif; ?></nav><?php endif; ?><?php endif; ?>
<?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
