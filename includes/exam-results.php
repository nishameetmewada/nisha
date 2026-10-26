<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/exam-grading.php';
require_once __DIR__ . '/commerce.php';

class ExamAttemptConflictException extends InvalidArgumentException {}

/** Shared website/app completion. Only canonical server content is accepted. */
function nisha_complete_exam(array $exam, array $payload, ?array $user): array
{
    $full = ($exam['session_type'] ?? 'full') === 'full';
    if ($full && $user === null) throw new NishaCommerceException('Sign in and start this full mock before submitting it.', 'mock_login_required', 401);
    $submission = nisha_validate_exam_submission($exam, $payload);
    if ($full) nisha_commerce_assert_mock(db(), (int)$user['id'], $exam['id'], $submission['attempt_id']);
    $result = nisha_grade_exam($exam, $submission);
    $envelope = ['success'=>true,'saved'=>false,'result_id'=>null,'result'=>$result,
        'save_message'=>'Your result is ready. Sign in before submitting to save future practice and mock results.'];
    if ($user === null) return $envelope;
    $pdo = null;
    try {
        $pdo = db();
        $pdo->beginTransaction();
        // Use the same user mutex as access reservation before touching the
        // result or trial ledger. Retries always return the first saved report.
        nisha_commerce_lock_user($pdo, (int)$user['id']);
        if ($full) nisha_commerce_assert_mock($pdo, (int)$user['id'], $exam['id'], $submission['attempt_id']);
        $q = $pdo->prepare('SELECT id,exam_id,result_json FROM exam_runs WHERE user_id=? AND attempt_id=? LIMIT 1');
        $q->execute([(int)$user['id'],$submission['attempt_id']]); $existing = $q->fetch();
        if ($existing) {
            if ($existing['exam_id'] !== $exam['id']) throw new ExamAttemptConflictException('This attempt ID belongs to another session. Start a new attempt.');
            $envelope['result_id'] = (int)$existing['id'];
            $envelope['result'] = json_decode($existing['result_json'],true,64,JSON_THROW_ON_ERROR);
        } else {
            $q = $pdo->prepare('INSERT INTO exam_runs(user_id,exam_id,attempt_id,exam_name,exam,responses_json,result_json,objective_score,objective_total,elapsed_seconds,assisted,completed_sections_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
            $q->execute([(int)$user['id'],$exam['id'],$submission['attempt_id'],$exam['name'],$exam['exam'],
                json_encode($submission['answers'],JSON_THROW_ON_ERROR),json_encode($result,JSON_THROW_ON_ERROR),
                $result['objective_score'],$result['objective_total'],$result['elapsed_seconds'],(int)$result['assisted'],json_encode($result['completed_sections'],JSON_THROW_ON_ERROR)]);
            $envelope['result_id'] = (int)$pdo->lastInsertId();
            $q = $pdo->prepare('SELECT exam_id,result_json FROM exam_runs WHERE id=? AND user_id=?');
            $q->execute([$envelope['result_id'],(int)$user['id']]); $stored = $q->fetch();
            if (!$stored || $stored['exam_id'] !== $exam['id']) throw new ExamAttemptConflictException('This attempt ID belongs to another session. Start a new attempt.');
            $envelope['result'] = json_decode($stored['result_json'],true,64,JSON_THROW_ON_ERROR);
        }
        if ($full) {
            nisha_commerce_finish_mock($pdo,(int)$user['id'],$exam['id'],$submission['attempt_id']);
            $envelope['mock_access'] = nisha_commerce_overview($pdo,(int)$user['id']);
        }
        $pdo->commit();
        $envelope['saved'] = true;
        $envelope['save_message'] = 'Saved to your NISHA account. You can return to this result anytime.';
    } catch (ExamAttemptConflictException|NishaCommerceException $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        $envelope['result_id'] = null;
        $envelope['save_message'] = 'Your result is ready, but saving is temporarily unavailable. Keep a copy and retry with the same attempt reference.';
    }
    return $envelope;
}
