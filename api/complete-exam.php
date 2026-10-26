<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/learning-catalog.php';
require_once dirname(__DIR__) . '/includes/exam-grading.php';
require_once dirname(__DIR__) . '/includes/commerce.php';
require_once dirname(__DIR__) . '/includes/exam-results.php';
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
try {
    $envelope = nisha_complete_exam($exam, $payload, current_user());
    exam_reply(200, $envelope);
} catch (NishaCommerceException $exception) {
    exam_reply($exception->httpStatus, ['error' => $exception->publicMessage, 'code' => $exception->errorCode, 'pricing_url' => 'pricing.php']);
} catch (ExamVersionConflictException $exception) {
    exam_reply(409, ['error' => $exception->getMessage(), 'code' => 'exam_version_changed']);
} catch (ExamAttemptConflictException $exception) {
    exam_reply(409, ['error' => $exception->getMessage(), 'code' => 'attempt_conflict']);
} catch (InvalidArgumentException $exception) {
    exam_reply(422, ['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('NISHA mock exam marking is temporarily unavailable: ' . get_class($exception));
    exam_reply(503, ['error' => 'This exam could not be marked right now. Your answers are retained so you can retry.']);
}
