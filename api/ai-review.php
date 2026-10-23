<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/includes/ai-review-service.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function ai_reply(int $status, array $body): never { http_response_code($status); echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES); exit; }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { header('Allow: GET, POST'); ai_reply(405, ['error' => 'Use POST to request AI feedback or GET to check a saved review reference.']); }
$user = current_user();
if ($user === null) ai_reply(401, ['error' => 'Sign in to request and save AI coaching.', 'code' => 'ai_login_required']);
$audio = null;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        session_write_close();
        $envelope = nisha_ai_request_status(db(), (int) $user['id'], $_GET['request_id'] ?? null);
        if ($envelope['state'] === 'pending') header('Retry-After: 10');
        ai_reply(match ($envelope['state']) { 'pending' => 202, 'not_found' => 404, default => 200 }, $envelope);
    }
    if (!verify_csrf(is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '')) ai_reply(403, ['error' => 'Your session expired. Refresh the page; keep a copy of your response.', 'code' => 'csrf_expired']);
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 11 * 1024 * 1024) ai_reply(413, ['error' => 'The upload is too large. Audio must be no more than 10 MB.']);
    if (preg_match('/\Aapplication\/json(?:\s*;|\s*\z)/i', $_SERVER['CONTENT_TYPE'] ?? '')) {
        $body = file_get_contents('php://input', false, null, 0, 100001);
        if (strlen($body) > 100000) ai_reply(413, ['error' => 'The response is too long.']);
        try { $payload = json_decode($body, true, 12, JSON_THROW_ON_ERROR); } catch (JsonException $exception) { ai_reply(400, ['error' => 'Send valid UTF-8 JSON.']); }
        if (!is_array($payload) || array_is_list($payload)) ai_reply(422, ['error' => 'Send a review request object.']);
    } elseif (preg_match('/\Amultipart\/form-data\s*;/i', $_SERVER['CONTENT_TYPE'] ?? '')) {
        $payload = $_POST;
        if (array_keys($_FILES) !== ['audio'] || !is_array($_FILES['audio']) || is_array($_FILES['audio']['error'] ?? null) || ($_FILES['audio']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['audio']['tmp_name'] ?? '')) ai_reply(422, ['error' => 'The recording could not be uploaded. Check its size and the server’s PHP upload limit.']);
        $file = $_FILES['audio'];
        if ($file['size'] > 10 * 1024 * 1024 || $file['size'] < 100) ai_reply(422, ['error' => 'Use a recording between 100 bytes and 10 MB.']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $audio = ['path' => $file['tmp_name'], 'mime' => $mime];
    } else ai_reply(415, ['error' => 'Send JSON for text or a multipart recording upload.']);
    session_write_close();
    if (function_exists('set_time_limit')) @set_time_limit(120);
    $envelope = nisha_ai_submit($user, $payload, $audio);
} catch (NishaAiException $exception) {
    if ($audio !== null && is_uploaded_file($audio['path'])) @unlink($audio['path']);
    if ($exception->errorCode === 'ai_in_progress') header('Retry-After: 10');
    ai_reply($exception->httpStatus, ['error' => $exception->publicMessage, 'code' => $exception->errorCode, 'retryable' => $exception->retryable]);
} catch (Throwable $exception) {
    if ($audio !== null && is_uploaded_file($audio['path'])) @unlink($audio['path']);
    error_log('NISHA AI review unavailable: ' . get_class($exception));
    ai_reply(503, ['error' => 'AI review is temporarily unavailable. The site owner should check the database migration and API setup. Your work is retained.', 'code' => 'ai_unavailable', 'retryable' => true]);
}
if ($audio !== null && is_uploaded_file($audio['path'])) @unlink($audio['path']);
ai_reply(200, $envelope);
