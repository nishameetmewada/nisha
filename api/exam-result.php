<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function result_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    result_reply(405, ['error' => 'Use GET to read a saved result.']);
}
$user = current_user();
if ($user === null) {
    result_reply(401, ['error' => 'Sign in to view your saved mock results.']);
}
$id = $_GET['id'] ?? null;
if (!is_string($id) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $id)) {
    result_reply(422, ['error' => 'Choose a valid saved result.']);
}
try {
    $statement = db()->prepare('SELECT id, result_json FROM exam_runs WHERE id = ? AND user_id = ? LIMIT 1');
    $statement->execute([$id, $user['id']]);
    $row = $statement->fetch();
    if (!$row) {
        result_reply(404, ['error' => 'This saved result was not found in your account.']);
    }
    $result = json_decode($row['result_json'], true, 32, JSON_THROW_ON_ERROR);
    result_reply(200, [
        'success' => true, 'saved' => true, 'result_id' => (int) $row['id'],
        'save_message' => 'Saved to your NISHA dashboard.', 'result' => $result,
    ]);
} catch (Throwable $exception) {
    result_reply(503, ['error' => 'Saved mock results are temporarily unavailable. Please try again shortly.']);
}
