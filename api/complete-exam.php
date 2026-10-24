<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/learning-catalog.php';
require_once dirname(__DIR__) . '/includes/exam-grading.php';
require_once dirname(__DIR__) . '/includes/commerce.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function exam_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    exam_reply(405, ['error' => 'Use POST to complete a practice session or mock exam.']);
}
if (!preg_match('/\Aapplication\/json(?:\s*;|\s*\z)/i', $_SERVER['CONTENT_TYPE'] ?? '')) {
    exam_reply(415, ['error' => 'Send your exam responses as JSON.']);
}
$token = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!verify_csrf($token)) {
    exam_reply(403, ['error' => 'Your session expired. Refresh the page and retry; your saved answers stay in this browser.']);
}
$maxBytes = 2 * 1024 * 1024;
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes) {
    exam_reply(413, ['error' => 'These exam responses exceed the two-megabyte limit. Audio uploads are not accepted.']);
}
$body = (string) file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if (strlen($body) > $maxBytes) {
    exam_reply(413, ['error' => 'These exam responses are too large.']);
}
try {
    $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    exam_reply(400, ['error' => 'The exam responses are not valid UTF-8 JSON.']);
}
if (!is_array($payload) || array_is_list($payload)) {
    exam_reply(422, ['error' => 'Send a JSON object containing your exam responses.']);
}
$examId = $payload['exam_id'] ?? null;
try {
    $exam = is_string($examId) ? nisha_resolve_exam($examId) : null;
} catch (Throwable $exception) {
    error_log('NISHA learning content is unavailable: ' . get_class($exception));
    exam_reply(503, ['error' => 'This learning content is temporarily unavailable. Your answers stay in this browser so you can retry.']);
}
if ($exam === null) {
    exam_reply(422, ['error' => 'This practice session or mock exam is not available.']);
}
$isFullMock = ($exam['session_type'] ?? 'full') === 'full';
$user = current_user();
if ($isFullMock && $user === null) exam_reply(401, ['error' => 'Sign in and open your full mock before submitting it.', 'code' => 'mock_login_required']);
try {
    $submission = nisha_validate_exam_submission($exam, $payload);
    if ($isFullMock) nisha_commerce_assert_mock(db(), (int) $user['id'], $examId, $submission['attempt_id']);
    $result = nisha_grade_exam($exam, $submission);
} catch (NishaCommerceException $exception) {
    exam_reply($exception->httpStatus, ['error' => $exception->publicMessage, 'code' => $exception->errorCode, 'pricing_url' => 'pricing.php']);
} catch (ExamVersionConflictException $exception) {
    exam_reply(409, ['error' => $exception->getMessage(), 'code' => 'exam_version_changed']);
} catch (InvalidArgumentException $exception) {
    exam_reply(422, ['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('NISHA mock exam marking is temporarily unavailable: ' . $examId);
    exam_reply(503, ['error' => 'This exam could not be marked right now. Your answers are retained in this browser so you can retry.']);
}
$envelope = [
    'success' => true, 'saved' => false, 'result_id' => null,
    'save_message' => 'Your result is ready. Sign in or create an account before submitting to save future practice and mock results. You can download this result now.',
    'result' => $result,
];
if ($user !== null) {
    $saveConnection = null;
    try {
        $saveConnection = db();
        // A full result and its free-trial consumption form one durable save.
        // If either part fails, retrying retains the same original attempt.
        if ($isFullMock) $saveConnection->beginTransaction();
        $statement = db()->prepare('SELECT id, exam_id, result_json FROM exam_runs WHERE user_id = ? AND attempt_id = ? LIMIT 1');
        $statement->execute([$user['id'], $submission['attempt_id']]);
        $existing = $statement->fetch();
        if ($existing) {
            if ($existing['exam_id'] !== $examId) {
                exam_reply(409, ['error' => 'This attempt ID belongs to another session. Start a new attempt.']);
            }
            $envelope['result'] = json_decode($existing['result_json'], true, 32, JSON_THROW_ON_ERROR);
            $envelope['result_id'] = (int) $existing['id'];
        } else {
            $statement = db()->prepare('INSERT INTO exam_runs (user_id, exam_id, attempt_id, exam_name, exam, responses_json, result_json, objective_score, objective_total, elapsed_seconds, assisted, completed_sections_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
            $statement->execute([
                $user['id'], $examId, $submission['attempt_id'], $exam['name'], $exam['exam'],
                json_encode($submission['answers'], JSON_THROW_ON_ERROR), json_encode($result, JSON_THROW_ON_ERROR),
                $result['objective_score'], $result['objective_total'], $result['elapsed_seconds'], (int) $result['assisted'],
                json_encode($result['completed_sections'], JSON_THROW_ON_ERROR),
            ]);
            $envelope['result_id'] = (int) db()->lastInsertId();
            // A retry/race always returns the original immutable stored result.
            $statement = db()->prepare('SELECT exam_id, result_json FROM exam_runs WHERE id = ? AND user_id = ?');
            $statement->execute([$envelope['result_id'], $user['id']]);
            $stored = $statement->fetch();
            if (!$stored || $stored['exam_id'] !== $examId) {
                exam_reply(409, ['error' => 'This attempt ID belongs to another session. Start a new attempt.']);
            }
            $envelope['result'] = json_decode($stored['result_json'], true, 32, JSON_THROW_ON_ERROR);
        }
        if ($isFullMock) {
            nisha_commerce_finish_mock(db(), (int) $user['id'], $examId, $submission['attempt_id']);
            $envelope['mock_access'] = nisha_commerce_overview(db(), (int) $user['id']);
            $saveConnection->commit();
        }
        $envelope['saved'] = true;
        $envelope['save_message'] = 'Saved to your NISHA dashboard. You can return to this result anytime.';
    } catch (Throwable $exception) {
        if ($saveConnection instanceof PDO && $saveConnection->inTransaction()) $saveConnection->rollBack();
        $envelope['result_id'] = null;
        $envelope['save_message'] = 'Your result is ready, but saving is temporarily unavailable. Download a copy and retry saving when your connection is restored.';
    }
} elseif (isset($_SESSION['user_id'])) {
    $envelope['save_message'] = 'Your result is ready, but account saving is temporarily unavailable. Download a copy and try again shortly.';
}
exam_reply(200, $envelope);
