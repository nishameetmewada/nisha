<?php
declare(strict_types=1);

// Local-only regression using a disposable database and least-privilege user.
// No application credentials/configuration or existing tables are modified.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$admin = getenv('NISHA_TEST_MYSQL_ADMIN_BIN') ?: '/workspace/.tools/bin/mariadb';
if (!is_executable($admin)) {
    fwrite(STDERR, "A local MariaDB admin client is needed. Set NISHA_TEST_MYSQL_ADMIN_BIN to its executable; it must use private local client configuration.\n");
    exit(1);
}
$suffix = bin2hex(random_bytes(7));
$database = 'nisha_auth_test_' . $suffix;
$databaseUser = 'nisha_auth_' . $suffix;
$password = bin2hex(random_bytes(24));
$scratch = sys_get_temp_dir() . '/nisha-auth-database-' . $suffix;
$checks = 0;
$failed = false;

/** @return array{code:int, output:string} */
function auth_process(array $command, string $input = ''): array
{
    global $scratch;
    if ($command[0] === PHP_BINARY) {
        $settings = php_ini_loaded_file() && !in_array('-n', $command, true) ? ['-c', php_ini_loaded_file()] : [];
        array_push($settings, '-d', 'session.save_path=' . $scratch . '/sessions', '-d', 'display_errors=stderr');
        array_splice($command, 1, 0, $settings);
    }
    $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start the local test process.');
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    // Do not echo raw database errors: they can contain connection details.
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'output' => $output === false ? '' : $output];
}

function auth_check(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($description);
    ++$checks;
    echo 'PASS: ' . $description . "\n";
}

function auth_sql(string $sql): void
{
    global $admin;
    if (auth_process([$admin], $sql)['code'] !== 0) throw new RuntimeException('Disposable local database setup/cleanup failed.');
}

function auth_config(array $overrides = []): void
{
    global $scratch, $database, $databaseUser, $password;
    $configuration = array_replace([
        'db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => $database,
        'db_user' => $databaseUser, 'db_password' => $password, 'openai_api_key' => '',
    ], $overrides);
    file_put_contents($scratch . '/config/config.php', '<?php return ' . var_export($configuration, true) . ';');
    chmod($scratch . '/config/config.php', 0600);
}

function auth_status(array $phpOptions = []): array
{
    global $scratch;
    $result = auth_process([PHP_BINARY, ...$phpOptions, '-r', 'require $argv[1]; echo json_encode(account_database_status());', $scratch . '/includes/bootstrap.php']);
    if ($result['code'] !== 0) throw new RuntimeException('Account status check failed to execute.');
    return json_decode($result['output'], true, 8, JSON_THROW_ON_ERROR);
}

try {
    mkdir($scratch, 0700);
    mkdir($scratch . '/config', 0700);
    mkdir($scratch . '/includes', 0700);
    mkdir($scratch . '/sessions', 0700);
    copy($root . '/includes/bootstrap.php', $scratch . '/includes/bootstrap.php');
    copy($root . '/check-setup.php', $scratch . '/check-setup.php');
    auth_sql("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
        . "CREATE USER '$databaseUser'@'127.0.0.1' IDENTIFIED BY '$password';\n"
        . "GRANT SELECT, INSERT, UPDATE, DELETE ON `$database`.* TO '$databaseUser'@'127.0.0.1';\n");
    auth_config(['db_name' => '', 'db_user' => '']);
    auth_check(auth_status() === ['ready' => false, 'code' => 'missing_configuration'], 'Blank private configuration is identified without attempting an account creation.');
    auth_config();
    auth_check(auth_status(['-n']) === ['ready' => false, 'code' => 'missing_mysql_driver'], 'A missing MySQL extension has a separate safe diagnostic.');
    $emptyStatus = auth_status();
    auth_check($emptyStatus === ['ready' => false, 'code' => 'missing_accounts_table'], 'A connected database without users cannot claim account readiness (' . $emptyStatus['code'] . ').');
    auth_sql("CREATE TABLE `$database`.users (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY);");
    auth_check(auth_status() === ['ready' => false, 'code' => 'outdated_accounts_table'], 'A users table missing authentication columns is rejected.');
    auth_sql("DROP TABLE `$database`.users;");
    if (!preg_match('/CREATE TABLE IF NOT EXISTS users \(.*?\) ENGINE=.*?;/s', (string) file_get_contents($root . '/database/schema.sql'), $matches)) {
        throw new RuntimeException('Could not locate the canonical users schema.');
    }
    auth_sql("USE `$database`;\n" . $matches[0]);
    auth_check(auth_status() === ['ready' => true, 'code' => 'ready'], 'Accounts are ready with users only; missing practice and AI tables do not lock out students.');
    $roundtrip = auth_process([PHP_BINARY, '-r', 'require $argv[1]; if (!database_ready()) exit(2); $email="isolated-auth@example.test"; $password="disposable-auth-password"; $q=db()->prepare("INSERT INTO users (name,email,password_hash) VALUES (?,?,?)"); $q->execute(["Isolated account",$email,password_hash($password,PASSWORD_DEFAULT)]); $q=db()->prepare("SELECT password_hash FROM users WHERE email=?"); $q->execute([$email]); if (!password_verify($password,(string)$q->fetchColumn())) exit(3); echo "registered-and-verified";', $scratch . '/includes/bootstrap.php']);
    auth_check($roundtrip['code'] === 0 && $roundtrip['output'] === 'registered-and-verified', 'Real account insertion and password verification work without history tables.');
    $report = auth_process([PHP_BINARY, $scratch . '/check-setup.php']);
    auth_check($report['code'] === 1 && str_contains($report['output'], 'PASS: The account database') && str_contains($report['output'], 'FAIL: Short practice history'), 'Private setup check separates working accounts from missing result storage.');
    foreach ([$database, $databaseUser, $password, 'isolated-auth@example.test'] as $privateValue) {
        auth_check(!str_contains($report['output'], $privateValue), 'The setup report omits a private fixture value.');
    }
    auth_config(['db_password' => 'deliberately-wrong-disposable-password']);
    auth_check(auth_status() === ['ready' => false, 'code' => 'database_access_denied'], 'Invalid database authentication gives a safe access diagnostic.');
    auth_config(['db_port' => 1]);
    auth_check(auth_status() === ['ready' => false, 'code' => 'database_connection_failed'], 'An unreachable local database gives a connection diagnostic.');
    file_put_contents($scratch . '/config/config.php', '<?php return ["db_password" => ');
    auth_check(auth_status() === ['ready' => false, 'code' => 'invalid_configuration'], 'A PHP syntax error in private configuration produces a fixed diagnostic.');
    file_put_contents($scratch . '/config/config.php', 'accidentally-plain-text-private-setting=' . $password);
    $plainTextReport = auth_process([PHP_BINARY, $scratch . '/check-setup.php']);
    auth_check($plainTextReport['code'] === 1 && !str_contains($plainTextReport['output'], $password)
        && !str_contains($plainTextReport['output'], 'accidentally-plain-text'), 'The private CLI checker discards accidental plain-text config output.');
    echo "Account database checks passed: $checks\n";
} catch (Throwable $exception) {
    // Exception messages above are fixed test descriptions, never DB messages.
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    $failed = true;
} finally {
    try {
        auth_sql("DROP DATABASE IF EXISTS `$database`;\nDROP USER IF EXISTS '$databaseUser'@'127.0.0.1';\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, "Disposable local auth database cleanup failed.\n");
        $failed = true;
    }
    foreach (['config/config.php', 'includes/bootstrap.php', 'check-setup.php'] as $relative) {
        if (is_file($scratch . '/' . $relative)) unlink($scratch . '/' . $relative);
    }
    foreach (glob($scratch . '/sessions/*') ?: [] as $session) unlink($session);
    foreach (['config', 'includes', 'sessions', ''] as $relative) {
        if (is_dir($scratch . '/' . $relative)) rmdir($scratch . '/' . $relative);
    }
}
exit($failed ? 1 : 0);
