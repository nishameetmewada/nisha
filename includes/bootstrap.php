<?php
declare(strict_types=1);

// Shared application bootstrapping. No database is needed for public practice.
if (!defined('NISHA_ROOT')) {
    define('NISHA_ROOT', dirname(__DIR__));
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('nisha_session');
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function config(?string $key = null, mixed $default = null): mixed
{
    static $configuration;
    if ($configuration === null) {
        $configuration = [
            'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
            'db_port' => getenv('DB_PORT') ?: 3306,
            'db_name' => getenv('DB_NAME') ?: '',
            'db_user' => getenv('DB_USER') ?: '',
            'db_password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        ];
        $localFile = NISHA_ROOT . '/config/config.php';
        if (is_file($localFile)) {
            $localConfiguration = require $localFile;
            if (is_array($localConfiguration)) {
                $configuration = array_replace($configuration, $localConfiguration);
            }
        }
    }

    return $key === null ? $configuration : ($configuration[$key] ?? $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

class DatabaseUnavailableException extends RuntimeException
{
}

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }
    if (config('db_name') === '' || config('db_user') === '') {
        throw new DatabaseUnavailableException('Student accounts are not configured yet.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new DatabaseUnavailableException('The MySQL driver is unavailable.');
    }

    try {
        $connection = new PDO(
            'mysql:host=' . config('db_host') . ';port=' . (int) config('db_port')
                . ';dbname=' . config('db_name') . ';charset=utf8mb4',
            (string) config('db_user'),
            (string) config('db_password'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
        $connection->exec("SET time_zone = '+00:00'");
    } catch (PDOException $exception) {
        throw new DatabaseUnavailableException('Student accounts are temporarily unavailable.', 0, $exception);
    }

    return $connection;
}

function database_ready(): bool
{
    static $ready;
    if ($ready !== null) {
        return $ready;
    }
    try {
        db()->query('SELECT id FROM users LIMIT 1');
        db()->query('SELECT id FROM attempts LIMIT 1');
        return $ready = true;
    } catch (Throwable $exception) {
        return $ready = false;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    $token ??= is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    return is_string($_SESSION['csrf_token'] ?? null) && $token !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

function current_user(): ?array
{
    static $resolved = false;
    static $user;
    if ($resolved) {
        return $user;
    }
    $resolved = true;
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) || $userId < 1) {
        return null;
    }

    try {
        $statement = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch() ?: null;
        if ($user === null) {
            unset($_SESSION['user_id']);
        }
    } catch (Throwable $exception) {
        $user = null;
    }
    return $user;
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

function sign_in(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['login_failures'], $_SESSION['login_blocked_until']);
}
