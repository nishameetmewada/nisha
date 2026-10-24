<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/includes/teachers.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function teacher_progress_reply(int $status, array $payload): never
{ http_response_code($status); echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); teacher_progress_reply(405, ['error' => 'Use POST to save video progress.']); }
$user = current_user();
if (!$user) teacher_progress_reply(401, ['error' => 'Sign in to track coaching progress.']);
if (!verify_csrf(is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '')) teacher_progress_reply(403, ['error' => 'Your session expired. Refresh this page and try again.']);
if (!preg_match('/\Aapplication\/json(?:\s*;|\s*\z)/i', $_SERVER['CONTENT_TYPE'] ?? '')) teacher_progress_reply(415, ['error' => 'Send video progress as JSON.']);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) teacher_progress_reply(413, ['error' => 'This progress request is too large.']);
try {
    $raw = (string) file_get_contents('php://input', false, null, 0, 4097);
    if (strlen($raw) > 4096) teacher_progress_reply(413, ['error' => 'This progress request is too large.']);
    $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($input) || array_is_list($input)) throw new InvalidArgumentException('Send a valid video progress request.');
    teacher_progress_reply(200, nisha_teacher_watch($user, $input));
} catch (JsonException $exception) { teacher_progress_reply(400, ['error' => 'Video progress is not valid JSON.']); }
catch (InvalidArgumentException $exception) { teacher_progress_reply(422, ['error' => $exception->getMessage()]); }
catch (Throwable $exception) { error_log('NISHA coaching progress unavailable: ' . get_class($exception)); teacher_progress_reply(503, ['error' => 'Coaching progress is temporarily unavailable. You can keep watching.']); }
