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
            'openai_api_key' => getenv('NISHA_OPENAI_API_KEY') ?: (getenv('OPENAI_API_KEY') ?: ''),
            'openai_model' => getenv('NISHA_OPENAI_MODEL') ?: 'gpt-4.1-mini',
            'openai_transcription_model' => getenv('NISHA_OPENAI_TRANSCRIPTION_MODEL') ?: 'gpt-4o-mini-transcribe',
            'ai_reviews_per_student_day' => getenv('NISHA_AI_STUDENT_DAILY_LIMIT') ?: 10,
            'ai_reviews_per_day' => getenv('NISHA_AI_DAILY_LIMIT') ?: 100,
            'admin_user_ids' => getenv('NISHA_ADMIN_USER_IDS') ?: [],
            'teacher_rewards_enabled' => getenv('NISHA_TEACHER_REWARDS_ENABLED') ?: false,
            'teacher_reward_share_percent' => getenv('NISHA_TEACHER_REWARD_SHARE_PERCENT') ?: 90,
            'teacher_reward_currency' => getenv('NISHA_TEACHER_REWARD_CURRENCY') ?: 'AUD',
            'teacher_qualified_watch_percent' => getenv('NISHA_TEACHER_WATCH_PERCENT') ?: 70,
            'teacher_paid_views_only' => getenv('NISHA_TEACHER_PAID_VIEWS_ONLY') !== false ? getenv('NISHA_TEACHER_PAID_VIEWS_ONLY') : true,
            'teacher_video_hosts' => getenv('NISHA_TEACHER_VIDEO_HOSTS') ?: [],
        ];
        $localFile = NISHA_ROOT . '/config/config.php';
        if (is_file($localFile)) {
            $localConfiguration = require $localFile;
            if (is_array($localConfiguration)) {
                $configuration = array_replace($configuration, $localConfiguration);
            }
        }
        // Explicit AI environment settings also work with a copied example
        // config containing blank/default AI entries. Database precedence stays.
        foreach (['NISHA_OPENAI_API_KEY' => 'openai_api_key', 'NISHA_OPENAI_MODEL' => 'openai_model',
            'NISHA_OPENAI_TRANSCRIPTION_MODEL' => 'openai_transcription_model',
            'NISHA_AI_STUDENT_DAILY_LIMIT' => 'ai_reviews_per_student_day', 'NISHA_AI_DAILY_LIMIT' => 'ai_reviews_per_day'] as $variable => $setting) {
            $value = getenv($variable);
            if ($value !== false && trim($value) !== '') $configuration[$setting] = $value;
        }
        if ((getenv('NISHA_OPENAI_API_KEY') === false || trim((string) getenv('NISHA_OPENAI_API_KEY')) === '')
            && getenv('OPENAI_API_KEY') !== false && trim((string) getenv('OPENAI_API_KEY')) !== '') $configuration['openai_api_key'] = getenv('OPENAI_API_KEY');
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

/**
 * Account readiness is independent of optional practice/AI history tables.
 * The status contains only fixed diagnostic codes, never connection details.
 *
 * @return array{ready: bool, code: string}
 */
function account_database_status(): array
{
    static $status;
    if ($status !== null) {
        return $status;
    }
    try {
        if (trim((string) config('db_name')) === '' || trim((string) config('db_user')) === '') {
            return $status = ['ready' => false, 'code' => 'missing_configuration'];
        }
    } catch (Throwable $exception) {
        return $status = ['ready' => false, 'code' => 'invalid_configuration'];
    }
    if (!extension_loaded('pdo_mysql')) {
        return $status = ['ready' => false, 'code' => 'missing_mysql_driver'];
    }
    try {
        $connection = db();
    } catch (Throwable $exception) {
        $cause = $exception->getPrevious();
        $mysqlCode = $cause instanceof PDOException ? (int) ($cause->errorInfo[1] ?? 0) : 0;
        $code = match ($mysqlCode) {
            1044, 1045 => 'database_access_denied',
            1049 => 'database_not_found',
            default => 'database_connection_failed',
        };
        return $status = ['ready' => false, 'code' => $code];
    }
    try {
        // Validate every column used by sign-in/current_user, without reading
        // student records. An absent results table must not lock out accounts.
        $connection->query('SELECT id, name, email, password_hash, created_at FROM users LIMIT 0');
    } catch (PDOException $exception) {
        $code = match ((string) $exception->getCode()) {
            '42S02' => 'missing_accounts_table',
            '42S22' => 'outdated_accounts_table',
            default => 'accounts_table_unavailable',
        };
        return $status = ['ready' => false, 'code' => $code];
    } catch (Throwable $exception) {
        return $status = ['ready' => false, 'code' => 'accounts_table_unavailable'];
    }
    return $status = ['ready' => true, 'code' => 'ready'];
}

function database_ready(): bool
{
    return account_database_status()['ready'];
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
        require_once __DIR__ . '/admin-accounts.php';
        $sessionVersion = is_int($_SESSION['auth_version'] ?? null) ? $_SESSION['auth_version'] : 0;
        if (!nisha_account_session_valid(db(), $userId, $sessionVersion)) {
            unset($_SESSION['user_id'], $_SESSION['auth_version'], $_SESSION['csrf_token']);
            return null;
        }
        $statement = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch() ?: null;
        if ($user === null) {
            unset($_SESSION['user_id'], $_SESSION['auth_version']);
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
    require_once __DIR__ . '/admin-accounts.php';
    $version = nisha_account_login_version(db(), $userId);
    if ($version === null) {
        throw new InvalidArgumentException('This account is unavailable. Contact the site administrator.');
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['auth_version'] = $version;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['login_failures'], $_SESSION['login_blocked_until']);
}
