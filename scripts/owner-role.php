<?php
declare(strict_types=1);
// Private compatibility command: use the same audited, versioned role service.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
if ($argc !== 3 || !in_array($argv[1], ['grant-admin','revoke-admin'], true) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $argv[2])) {
    fwrite(STDERR, "Usage: php scripts/owner-role.php grant-admin ACCOUNT_ID\n       php scripts/owner-role.php revoke-admin ACCOUNT_ID\nRun privately using an existing numeric account ID. Import the updated schema first.\n"); exit(1);
}
try {
    require_once dirname(__DIR__) . '/includes/admin-accounts.php';
    $id = nisha_admin_accounts_bootstrap(db(), $argv[1] === 'grant-admin' ? 'promote' : 'revoke', ['user_id'=>$argv[2]]);
    fwrite(STDOUT, 'Administrator role updated for account ID ' . $id . ". Existing sessions have been invalidated.\n");
} catch (Throwable $exception) {
    $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Role setup failed. Check the private database configuration and import the updated schema.';
    fwrite(STDERR, $message . "\n"); exit(1);
}
