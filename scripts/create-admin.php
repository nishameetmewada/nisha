<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
if ($argc !== 3 || !in_array($argv[1], ['--email','--existing-id','create','promote'], true)) {
    fwrite(STDERR, "Usage: php scripts/create-admin.php --email EMAIL\n       php scripts/create-admin.php --existing-id ACCOUNT_ID\nCreation asks for a name and hidden password in a private terminal.\nNo password arguments, default credentials, or email-based promotions are accepted.\n"); exit(1);
}
function nisha_admin_terminal_password(string $prompt): string
{
    if (!function_exists('stream_isatty') || !stream_isatty(STDIN) || !function_exists('exec')) throw new RuntimeException('A private interactive terminal with stty is required. Register an account and promote its numeric ID instead.');
    $state = []; $code = 1; exec('stty -g < /dev/tty 2>/dev/null', $state, $code);
    if ($code !== 0 || count($state) !== 1 || !preg_match('/\A[0-9a-f:]+\z/iD', $state[0])) throw new RuntimeException('Hidden password entry is unavailable. Register an account and promote its numeric ID instead.');
    fwrite(STDOUT, $prompt); exec('stty -echo < /dev/tty 2>/dev/null', $unused, $code);
    if ($code !== 0) throw new RuntimeException('Hidden password entry is unavailable.');
    try { $line = fgets(STDIN); if ($line === false) throw new RuntimeException('Password entry was cancelled.'); return rtrim($line, "\r\n"); }
    finally { exec('stty ' . escapeshellarg($state[0]) . ' < /dev/tty 2>/dev/null'); fwrite(STDOUT, "\n"); }
}
try {
    require_once dirname(__DIR__) . '/includes/admin-accounts.php';
    if (in_array($argv[1], ['--email','create'], true)) {
        if (!filter_var($argv[2], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
        if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) throw new RuntimeException('Creation requires a private interactive terminal. Register normally and promote the numeric account ID instead.');
        fwrite(STDOUT, "Administrator display name: "); $name = fgets(STDIN);
        if ($name === false) throw new RuntimeException('Name entry was cancelled.');
        $password = nisha_admin_terminal_password('New password (12–72 bytes): ');
        $confirmation = nisha_admin_terminal_password('Confirm new password: ');
        $id = nisha_admin_accounts_bootstrap(db(), 'create', ['name'=>trim($name),'email'=>$argv[2],'password'=>$password,'password_confirmation'=>$confirmation]);
        unset($password, $confirmation);
    } else $id = nisha_admin_accounts_bootstrap(db(), 'promote', ['user_id'=>$argv[2]]);
    fwrite(STDOUT, 'Administrator access is ready for account ID ' . $id . ". Sign in using that account’s own password.\n");
} catch (Throwable $exception) {
    $message = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException && !$exception instanceof PDOException && !$exception instanceof DatabaseUnavailableException ? $exception->getMessage() : 'Administrator setup failed. Check the private database configuration and import the updated schema.';
    fwrite(STDERR, $message . "\n"); exit(1);
}
