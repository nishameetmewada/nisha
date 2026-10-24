<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/teachers.php';
require_once __DIR__ . '/includes/generated-library.php';
$user = require_login();
$approved = nisha_teacher_approved($user);
if (!$approved) http_response_code(403);
$pageTitle = 'Teacher content review'; $activePage = 'teacher'; $extraStyles = ['assets/css/content.css'];
$error = ''; $session = null; $status = 'Draft'; $missing = []; $parsed = null;
$message = is_string($_SESSION['content_review_flash'] ?? null) ? $_SESSION['content_review_flash'] : '';
unset($_SESSION['content_review_flash']);
$id = is_string($_POST['content_id'] ?? null) ? $_POST['content_id'] : (is_string($_GET['id'] ?? null) ? $_GET['id'] : 'gp.ielts-academic.00001');
if ($approved) {
    try {
        $parsed = nisha_generated_parse_id($id);
        if (!$parsed) throw new InvalidArgumentException('Choose an available draft reference.');
        if ($parsed['kind'] === 'question') {
            $q = db()->prepare('SELECT rv.payload_json,p.status FROM content_review_versions rv LEFT JOIN content_publications p ON p.version_id=rv.id WHERE rv.content_id=? ORDER BY rv.id DESC LIMIT 1');
            $q->execute([$id]); $latest = $q->fetch();
            $session = $latest ? json_decode($latest['payload_json'],true,64,JSON_THROW_ON_ERROR) : nisha_generated_question_session($parsed['course'],$parsed['number']);
            if ($latest) $status = $latest['status'] === 'published' ? 'Published reviewed version' : 'Previously reviewed · withdrawn';
        } else {
            foreach(nisha_generated_mock_sources($parsed['course'],$parsed['number']) as $section) foreach($section['question_ids'] as $questionId) if (!nisha_content_public_row($questionId)) $missing[] = $questionId;
            $session = nisha_generated_mock($parsed['course'],$parsed['number'],$missing ? null : true);
            $status = $missing ? 'Draft paper · questions need review' : 'Ready for whole-paper review';
        }
        $baseHash = nisha_content_payload_hash($session);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) throw new InvalidArgumentException('Your session expired. Reload the review page and try again.');
            if (!is_string($_POST['base_hash'] ?? null) || !hash_equals($baseHash,$_POST['base_hash'])) throw new InvalidArgumentException('This content changed while you were reviewing it. Reload and check the latest version.');
            if (($_POST['action'] ?? '') === 'withdraw') {
                nisha_content_withdraw($user,$id); $_SESSION['content_review_flash'] = 'Publication withdrawn. Papers depending on this question are hidden too.';
            } else {
                if ($parsed['kind'] === 'question') $session = nisha_content_edit_question($session,$_POST);
                else {
                    if ($missing) throw new InvalidArgumentException('Review and publish every referenced question before certifying the whole paper.');
                    $session['name'] = nisha_content_text($_POST['name'] ?? '', 'a paper title',180);
                }
                nisha_content_publish($user,$id,$session,nisha_content_text($_POST['review_note'] ?? '', 'a review note',2000),($_POST['attested'] ?? '') === '1');
                $_SESSION['content_review_flash'] = 'Your reviewed version is published. The frozen content and your review note have been recorded.';
            }
            redirect('content-review.php?' . http_build_query(['id'=>$id]));
        }
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { $error = 'The review database is unavailable. Ask the owner to import migration 006 and check database permissions. No content was published.'; }
}
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container content-page">
  <div class="content-heading"><p class="eyebrow">YOUR TEACHING JUDGEMENT COMES FIRST</p><h1>Review before students practise.</h1><p>Drafts are never public. Check the source, task, answer and explanation, make any corrections, then certify the version you actually reviewed.</p></div>
  <?php if (!$approved): ?><div class="content-empty"><h2>Teacher approval is required.</h2><p>An owner role alone cannot certify teacher-reviewed content. Apply as a teacher and wait for approval before opening the private review queue.</p><a class="btn btn-primary" href="teacher-portal.php">Open teacher portal →</a></div><?php else: ?>
  <p class="content-small">The private drafting pool offers up to 30,000 generated question variations and 3,000 assembled paper slots. Those are preparation targets, not independently written or published inventory. Reject duplicates and unsuitable tasks rather than approving a target number.</p>
  <form class="content-filter" method="get"><label for="review-id">Draft reference<input id="review-id" name="id" value="<?= e($id) ?>" maxlength="64" required></label><button class="btn btn-outline" type="submit">Open draft</button></form>
  <p class="content-small">Question reference: <code>gp.ielts-academic.00001</code> through <code>07500</code>. Paper reference: <code>gm.ielts-academic.0001</code> through <code>0750</code>. Other courses: <code>ielts-general</code>, <code>pte-academic</code>, <code>pte-core</code>.</p>
  <?php if($message): ?><div class="content-message" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if($error): ?><div class="content-message error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <?php if($session && $parsed): ?><p class="content-badge"><?= e($status) ?></p>
  <?php if ($parsed['kind'] === 'question'): $item=$session['sections'][0]['items'][0]; ?>
  <form method="post" class="content-review"><?= csrf_field() ?><input type="hidden" name="content_id" value="<?= e($id) ?>"><input type="hidden" name="base_hash" value="<?= e($baseHash) ?>">
    <label>Student-facing title<input name="name" maxlength="180" value="<?= e($session['name']) ?>" required></label>
    <p><strong><?= e($item['task_type']) ?></strong> · <?= e(ucfirst(str_replace('generated-','',$session['sections'][0]['id']))) ?></p>
    <label>Question prompt<textarea name="prompt" required><?= e($item['prompt']) ?></textarea></label>
    <label>Instructions<textarea name="instructions"><?= e($item['instructions'] ?? '') ?></textarea></label>
    <?php foreach($session['resources'] as $resourceId=>$resource): ?><fieldset><legend><?= e(ucfirst($resource['kind'])) ?> source</legend><label>Source title<input name="resource_title[<?= e($resourceId) ?>]" maxlength="250" value="<?= e($resource['title']) ?>" required></label>
      <?php if($resource['kind'] !== 'chart'): ?><label><?= $resource['kind']==='audio'?'Spoken script':'Passage paragraphs (blank line between paragraphs)' ?><textarea rows="12" name="resource_text[<?= e($resourceId) ?>]" required><?= e($resource['kind']==='audio'?$resource['audio_script']:implode("\n\n",$resource['paragraphs'])) ?></textarea></label>
      <?php else: ?><p><?= e($resource['description'] ?? '') ?></p><div style="overflow-x:auto"><table class="content-source-table"><caption><?= e($resource['chart']['unit'] ?? 'Chart values') ?></caption><thead><tr><th>Category</th><?php foreach($resource['chart']['series'] as $series): ?><th><?= e($series['name']) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($resource['chart']['categories'] as $vi=>$category): ?><tr><th><?= e($category) ?></th><?php foreach($resource['chart']['series'] as $si=>$series): ?><td><input aria-label="<?= e($category.' · '.$series['name']) ?>" name="chart_values[<?= e($resourceId) ?>][<?= (int)$si ?>][<?= (int)$vi ?>]" value="<?= e($series['values'][$vi]) ?>" required></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </fieldset><?php endforeach; ?>
    <?php if(isset($item['options'])): ?><fieldset><legend>Answer options</legend><?php foreach($item['options'] as $option): ?><label><?= e(strtoupper($option['id'])) ?><textarea name="options[<?= e($option['id']) ?>]" rows="2" required><?= e($option['text']) ?></textarea></label><?php endforeach; ?></fieldset><?php endif; ?>
    <?php if($item['type']==='choice'): ?><label>Correct answer<select name="answers"><?php foreach($item['options'] as $option): ?><option value="<?= e($option['id']) ?>" <?= $item['answer']===$option['id']?'selected':'' ?>><?= e(strtoupper($option['id']).' · '.$option['text']) ?></option><?php endforeach; ?></select></label>
    <?php elseif(in_array($item['type'],['multi','reorder','highlight'],true)): ?><label><?= $item['type']==='reorder'?'Correct option order, separated by commas':($item['type']==='highlight'?'Correct word positions, starting at zero, separated by commas':'Correct option letters, separated by commas') ?><input name="answers" value="<?= e(implode(',',$item['answer'])) ?>" required></label>
    <?php elseif($item['type']==='short'): ?><label>Accepted answers (one variant per line)<textarea name="answers" required><?= e(implode("\n",$item['answer'])) ?></textarea></label>
    <?php elseif($item['type']==='gaps'): ?><label>Question text (preserve each gap marker)<textarea name="question_text" required><?= e($item['text']) ?></textarea></label><?php foreach($item['blanks'] as $blank): ?><label>Accepted answers for <?= e($blank['id']) ?> (one per line)<textarea name="gap_answers[<?= e($blank['id']) ?>]" required><?= e(implode("\n",$blank['answer'])) ?></textarea></label><?php endforeach; ?>
    <?php endif; ?>
    <?php if($item['type']==='highlight'): ?><label>Displayed comparison text<textarea name="question_text" required><?= e($item['text']) ?></textarea></label><label>Corrected comparison text<textarea name="correct_text" required><?= e($item['correct_text']) ?></textarea></label><?php endif; ?>
    <?php if(in_array($item['type'],['essay','spoken'],true)): ?><label>Review rubric (one criterion per line)<textarea name="rubric" required><?= e(implode("\n",$item['rubric'])) ?></textarea></label><?php endif; ?>
    <label>Answer explanation<textarea name="explanation"><?= e($item['explanation'] ?? '') ?></textarea></label>
    <label>Teacher review note<textarea name="review_note" minlength="20" maxlength="2000" placeholder="Explain what you checked and any changes made." required></textarea></label>
    <label class="review-confirm"><input type="checkbox" name="attested" value="1" required><span>I have checked this exact source, question, correct answer and explanation. The question is suitable for this course and is not a duplicate added to inflate the library count.</span></label>
    <div class="content-actions"><button class="btn btn-primary" type="submit" name="action" value="publish">Certify and publish reviewed version</button><button class="btn btn-outline" type="submit" name="action" value="withdraw" formnovalidate>Withdraw publication</button></div>
  </form>
  <?php else: ?>
    <?php if($missing): ?><div class="content-message"><strong><?= count($missing) ?> questions still need review.</strong><p>This paper cannot be published yet. Open its question references below, review and publish each one, then return for whole-paper review.</p></div><?php endif; ?>
    <div class="content-review"><h2><?= e($session['name']) ?></h2><p><?= e($session['format_note']) ?></p><p>The assembled task mix is learning practice, not a replica of an official test. Check the overall difficulty, timing and coherence as well as each answer.</p>
    <?php foreach(nisha_generated_mock_sources($parsed['course'],$parsed['number']) as $si=>$definition): ?><h3><?= e($definition['base']['title']) ?></h3><ol class="content-preview-list"><?php foreach($definition['question_ids'] as $ii=>$questionId): $paperItem=$session['sections'][$si]['items'][$ii]; $resource=$session['resources'][$paperItem['resource_id']]; ?><li><a href="content-review.php?<?= e(http_build_query(['id'=>$questionId])) ?>"><?= e($questionId) ?> · <?= in_array($questionId,$missing,true)?'Needs review':'Reviewed' ?></a><h3><?= e($paperItem['task_type']) ?></h3><p><?= e($paperItem['prompt']) ?></p><details><summary>Read source and answer</summary><div class="content-preview"><?= e($resource['kind']==='audio'?$resource['audio_script']:($resource['kind']==='passage'?implode("\n\n",$resource['paragraphs']):json_encode($resource['chart'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))) ?></div><p><?= e($paperItem['explanation'] ?? implode(' · ',$paperItem['rubric'] ?? [])) ?></p><?php if(isset($paperItem['options'])): ?><ul><?php foreach($paperItem['options'] as $option): ?><li><?= e(strtoupper($option['id']).': '.$option['text']) ?></li><?php endforeach; ?></ul><?php endif; ?><?php if(isset($paperItem['answer'])): ?><p>Accepted answer/order: <?= e(is_array($paperItem['answer'])?implode(', ',$paperItem['answer']):$paperItem['answer']) ?></p><?php endif; ?></details></li><?php endforeach; ?></ol><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="content_id" value="<?= e($id) ?>"><input type="hidden" name="base_hash" value="<?= e($baseHash) ?>"><label>Paper title<input name="name" maxlength="180" value="<?= e($session['name']) ?>" required></label><label>Whole-paper review note<textarea name="review_note" minlength="20" maxlength="2000" required></textarea></label><label class="review-confirm"><input type="checkbox" name="attested" value="1" required><span>I checked the whole assembled paper and its current reviewed question versions, including task suitability, difficulty, timing and answer accuracy.</span></label><button class="btn btn-primary" name="action" value="publish" type="submit" <?= $missing?'disabled':'' ?>>Certify and publish reviewed paper</button></form></div>
  <?php endif; endif; endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
