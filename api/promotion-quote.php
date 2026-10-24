<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/commerce.php';
require_once dirname(__DIR__) . '/includes/promotions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
function promotion_quote_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    promotion_quote_reply(405, ['error' => 'Use POST to check a promotion code.']);
}
$student = current_user();
if (!$student) promotion_quote_reply(401, ['error' => 'Sign in to check your promotion code.']);
$token = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!verify_csrf($token)) promotion_quote_reply(403, ['error' => 'Your session expired. Refresh this page before checking a code.']);
if (!preg_match('/\Aapplication\/json(?:\s*;|\s*\z)/i', $_SERVER['CONTENT_TYPE'] ?? '')) promotion_quote_reply(415, ['error' => 'Send the promotion code as JSON.']);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) promotion_quote_reply(413, ['error' => 'This promotion request is too large.']);
try {
    $body = (string) file_get_contents('php://input', false, null, 0, 4097);
    if (strlen($body) > 4096) promotion_quote_reply(413, ['error' => 'This promotion request is too large.']);
    $payload = json_decode($body, true, 4, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || array_keys($payload) !== ['promotion_code'] || !is_string($payload['promotion_code']) || strlen($payload['promotion_code']) > 40) {
        promotion_quote_reply(422, ['error' => 'Send only a promotion code of at most 40 characters.']);
    }
    $settings = nisha_commerce_settings();
    if (!$settings['price_ready']) promotion_quote_reply(503, ['error' => 'The access pass price is being configured.']);
    $quote = nisha_promotions_quote(db(), (int) $student['id'], $payload['promotion_code'], $settings);
    $public = array_intersect_key($quote, array_flip(['base_amount_minor', 'discount_minor', 'amount_minor', 'currency', 'access_days', 'code', 'public_label']));
    $public['formatted_total'] = nisha_commerce_money((int) $quote['amount_minor'], $quote['currency']);
    $public['formatted_discount'] = nisha_commerce_money((int) $quote['discount_minor'], $quote['currency']);
    promotion_quote_reply(200, ['success' => true, 'quote' => $public]);
} catch (JsonException $exception) {
    promotion_quote_reply(400, ['error' => 'The promotion request is not valid JSON.']);
} catch (NishaCommerceException $exception) {
    promotion_quote_reply($exception->httpStatus, ['error' => $exception->publicMessage, 'code' => $exception->errorCode]);
} catch (Throwable $exception) {
    promotion_quote_reply(503, ['error' => 'Promotion codes are temporarily unavailable. Please try again later.']);
}
