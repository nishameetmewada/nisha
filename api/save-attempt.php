<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Use POST to save a practice result.']);
}
if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    respond(415, ['error' => 'Send your practice result as JSON.']);
}
$token = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!verify_csrf($token)) {
    respond(403, ['error' => 'Your session expired. Refresh the page and try again.']);
}
$user = current_user();
if ($user === null) {
    respond(401, ['error' => 'Sign in to save your practice results.']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    respond(413, ['error' => 'This practice result is too large.']);
}
try {
    $input = json_decode((string) file_get_contents('php://input', false, null, 0, 8193), true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    respond(400, ['error' => 'The practice result is not valid JSON.']);
}
if (!is_array($input) || array_is_list($input)) {
    respond(422, ['error' => 'Enter a valid practice result.']);
}
$exam = $input['exam'] ?? null;
$skill = $input['skill'] ?? null;
$score = $input['score'] ?? null;
$total = $input['total'] ?? null;
$duration = $input['duration_seconds'] ?? null;
if (!in_array($exam, ['IELTS', 'PTE'], true)
    || !in_array($skill, ['Reading', 'Listening'], true)
    || !is_int($score) || !is_int($total) || !is_int($duration)
    || $total < 1 || $total > 100 || $score < 0 || $score > $total
    || $duration < 0 || $duration > 86400) {
    respond(422, ['error' => 'Enter valid reading or listening results, with a score between zero and the question total.']);
}
if (microtime(true) - (float) ($_SESSION['last_attempt_saved'] ?? 0) < 1) {
    respond(429, ['error' => 'Please wait a moment before saving another result.']);
}
$saveConnection = null;
try {
    require_once dirname(__DIR__) . '/includes/commerce.php';
    $saveConnection = db();
    $saveConnection->beginTransaction();
    nisha_commerce_lock_user($saveConnection, (int)$user['id']);
    $statement = db()->prepare('INSERT INTO attempts (user_id, exam, skill, score, total, duration_seconds) VALUES (?, ?, ?, ?, ?, ?)');
    $statement->execute([$user['id'], $exam, $skill, $score, $total, $duration]);
    $_SESSION['last_attempt_saved'] = microtime(true);
    $savedId = (int)db()->lastInsertId();
    db()->commit();
    respond(201, ['success' => true, 'id' => $savedId]);
} catch (Throwable $exception) {
    if ($saveConnection instanceof PDO && $saveConnection->inTransaction()) $saveConnection->rollBack();
    respond(503, ['error' => 'We could not save this result right now. Please try again shortly.']);
}
