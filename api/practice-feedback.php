<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/learning-catalog.php';
require_once dirname(__DIR__) . '/includes/exam-grading.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function feedback_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    feedback_reply(405, ['error' => 'Use POST to check a practice response.']);
}
if (!preg_match('/\Aapplication\/json(?:\s*;|\s*\z)/i', $_SERVER['CONTENT_TYPE'] ?? '')) {
    feedback_reply(415, ['error' => 'Send your practice response as JSON.']);
}
$token = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!verify_csrf($token)) {
    feedback_reply(403, ['error' => 'Your session expired. Refresh the page and retry; your answers stay in this browser.']);
}
$maxBytes = 262144;
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes) {
    feedback_reply(413, ['error' => 'This practice response is too large. Audio uploads are not accepted.']);
}
$body = (string) file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if (strlen($body) > $maxBytes) {
    feedback_reply(413, ['error' => 'This practice response is too large.']);
}
try {
    $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    feedback_reply(400, ['error' => 'The practice response is not valid UTF-8 JSON.']);
}
$keys = ['exam_id', 'exam_version', 'item_id', 'response'];
if (!is_array($payload) || array_is_list($payload)
    || array_diff(array_keys($payload), $keys) !== [] || array_diff($keys, array_keys($payload)) !== []) {
    feedback_reply(422, ['error' => 'Send the practice ID and version, question ID, and your response.']);
}
try {
    $exam = is_string($payload['exam_id']) ? nisha_resolve_exam($payload['exam_id']) : null;
    $items = $exam !== null && ($exam['session_type'] ?? 'full') === 'practice' ? nisha_exam_items($exam) : [];
} catch (Throwable $exception) {
    error_log('NISHA practice content is unavailable: ' . get_class($exception));
    feedback_reply(503, ['error' => 'This practice content is temporarily unavailable. Please try again shortly.']);
}
if ($exam === null) {
    feedback_reply(422, ['error' => 'Choose a known practice session.']);
}
if (($exam['session_type'] ?? 'full') !== 'practice') {
    feedback_reply(403, ['error' => 'Instant feedback is available in practice mode. Submit a timed mock to review its answers.']);
}
$itemId = $payload['item_id'];
if (!is_string($itemId) || strlen($itemId) > 80 || !isset($items[$itemId])) {
    feedback_reply(422, ['error' => 'Choose a question from this practice session.']);
}
try {
    // The existing marker receives exactly one canonical item. Client supplied
    // question definitions, answer keys and recordings are never accepted.
    $scoped = $exam;
    $scoped['sections'] = [];
    foreach ($exam['sections'] as $section) {
        foreach ($section['items'] as $item) {
            if ($item['id'] === $itemId) {
                $section['items'] = [$item];
                $scoped['sections'] = [$section];
                break 2;
            }
        }
    }
    $submission = nisha_validate_exam_submission($scoped, [
        'exam_id' => $exam['id'], 'exam_version' => $payload['exam_version'],
        'attempt_id' => 'feedback-' . bin2hex(random_bytes(12)),
        'answers' => [$itemId => $payload['response']], 'elapsed_seconds' => 0,
        'completed_sections' => [], 'assisted' => false,
    ]);
    $result = nisha_grade_exam($scoped, $submission);
    feedback_reply(200, ['success' => true, 'review' => $result['review'][0]]);
} catch (ExamVersionConflictException $exception) {
    feedback_reply(409, ['error' => $exception->getMessage(), 'code' => 'exam_version_changed']);
} catch (InvalidArgumentException $exception) {
    feedback_reply(422, ['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('NISHA practice feedback is unavailable: ' . get_class($exception));
    feedback_reply(503, ['error' => 'This response could not be checked right now. Please retry.']);
}
