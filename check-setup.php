<?php
declare(strict_types=1);

// Run privately from the website directory: php check-setup.php
// Never expose configuration diagnostics through an unauthenticated web route.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// A malformed config can emit text before PHP can validate its return value.
// Discard setup-loading output and keep errors to the fixed messages below.
ini_set('display_errors', '0');
$loadFailed = false;
ob_start();
try {
    require_once __DIR__ . '/includes/bootstrap.php';
    $status = account_database_status();
} catch (Throwable $exception) {
    $loadFailed = true;
} finally {
    ob_end_clean();
}
if ($loadFailed) {
    fwrite(STDERR, "FAIL: Could not load the private configuration. Check config/config.php syntax and file permissions.\n");
    exit(1);
}

$instructions = [
    'missing_configuration' => 'Create config/config.php from the example and enter the full database name and database user from hPanel.',
    'invalid_configuration' => 'Check config/config.php syntax and permissions. It must return a PHP array.',
    'missing_mysql_driver' => 'Enable pdo_mysql for this PHP version in hPanel.',
    'database_access_denied' => 'Check the database user, password and permission to access the selected database in hPanel.',
    'database_not_found' => 'Check the full database name, including its Hostinger account prefix, in hPanel.',
    'database_connection_failed' => 'Check the database host and port in hPanel and confirm the MySQL service is available.',
    'missing_accounts_table' => 'Select the configured database in phpMyAdmin and import database/schema.sql.',
    'outdated_accounts_table' => 'The users table does not match the NISHA schema. Back up the database and compare it with database/schema.sql before changing existing columns.',
    'accounts_table_unavailable' => 'Check that the database user can SELECT the users table and that its schema matches database/schema.sql.',
];

echo 'PHP: ' . PHP_VERSION . "\n";
if (!$status['ready']) {
    echo 'FAIL accounts [' . $status['code'] . ']: ' . ($instructions[$status['code']] ?? 'Check the database setup.') . "\n";
    exit(1);
}
echo "PASS: The account database and required users columns are available.\n";
echo "Account creation also requires INSERT permission; sign-in password upgrades require UPDATE permission.\n";

$requiredReady = true;
foreach ([
    'Short practice history' => 'SELECT id, user_id, exam, skill, score, total, duration_seconds, created_at FROM attempts LIMIT 0',
    'Practice and mock history' => 'SELECT id, user_id, exam_id, attempt_id, exam_name, exam, responses_json, result_json, objective_score, objective_total, elapsed_seconds, assisted, completed_sections_json, created_at FROM exam_runs LIMIT 0',
] as $label => $query) {
    try {
        db()->query($query);
        echo 'PASS: ' . $label . ".\n";
    } catch (Throwable $exception) {
        $requiredReady = false;
        echo 'FAIL: ' . $label . ". Check table read permissions and required columns. If the table is missing, import database/schema.sql; existing accounts and results are preserved. Importing does not repair incompatible existing columns.\n";
    }
}

$platformGroups = [
    'Mock access and payments' => ['commerce_settings', 'commerce_settings_audit', 'commerce_trials', 'mock_access_grants', 'mock_paid_sources', 'payment_orders', 'student_entitlements', 'payment_events', 'commerce_refunds'],
    'Teacher moderation and reward ledgers' => ['user_roles', 'teacher_profiles', 'coaching_videos', 'coaching_watch_sessions', 'coaching_watch_presence', 'coaching_qualified_views', 'teacher_policy_settings', 'teacher_reward_locks', 'teacher_profit_periods', 'teacher_reward_allocations'],
    'Teacher-reviewed content publications' => ['content_review_versions', 'content_publications', 'content_mock_dependencies'],
    'Discounts and promotions' => ['commerce_promotions', 'commerce_promotion_orders', 'commerce_promotions_audit'],
    'Administrator account controls' => ['account_security', 'admin_account_locks', 'admin_account_audit'],
    'Public website settings' => ['site_settings', 'site_settings_audit'],
    'Mobile accounts and deletion requests' => ['mobile_access_tokens', 'mobile_auth_limits', 'mobile_deletion_requests'],
];
foreach ($platformGroups as $label => $tables) {
    try {
        foreach ($tables as $table) db()->query('SELECT 1 FROM ' . $table . ' LIMIT 0');
        echo 'PASS: ' . $label . ".\n";
    } catch (Throwable $exception) {
        $requiredReady = false;
        echo 'SETUP REQUIRED: ' . $label . ". Import the new database/schema.sql or the matching 004–010 migrations; preserve existing data.\n";
    }
}

$aiReady = true;
try {
    db()->query('SELECT id, user_id, request_id, exam_id, exam_version, item_id, attempt_id, exam_name, task_type, mode, response_hash, cache_key, claim_token, status, response_text, review_json, error_code, claimed_at, created_at, updated_at FROM ai_reviews LIMIT 0');
    db()->query('SELECT usage_day, scope_key, used_count FROM ai_review_usage LIMIT 0');
} catch (Throwable $exception) {
    $aiReady = false;
}
echo $aiReady ? "PASS: AI review history and usage tables.\n" : "OPTIONAL AI SETUP: Check AI table read permissions and columns. Import database/migrations/003_ai_reviews.sql if the tables are missing; importing does not repair incompatible existing columns.\n";
echo trim((string) config('openai_api_key')) !== ''
    ? "PASS: An OpenAI key is configured. Provider access and billing have not been tested.\n"
    : "OPTIONAL AI SETUP: Add an OpenAI API key privately; see docs/ai-coaching.md.\n";
if (is_file(__DIR__ . '/includes/commerce.php')) {
    require_once __DIR__ . '/includes/commerce.php';
    $payments = nisha_commerce_settings();
    echo $payments['enabled'] ? "PASS: Checkout is enabled with server configuration present. No Stripe charge or webhook delivery was tested.\n"
        : "PAYMENT SETUP: Checkout is disabled until private Stripe credentials, signing secret and HTTPS site URL are configured and the owner enables it.\n";
}
session_write_close();
exit($requiredReady ? 0 : 1);
