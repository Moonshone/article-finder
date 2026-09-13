<?php

declare(strict_types=1);

const NEMA_ROOT = __DIR__ . '/..';
const NEMA_UPLOAD_DIR = NEMA_ROOT . '/uploads/stories';
const NEMA_UPLOAD_URL = '/uploads/stories/';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

function database(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    require NEMA_ROOT . '/../config.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Database connection unavailable.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $connection = $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function security_headers(bool $admin = false): void
{
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; upgrade-insecure-requests");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($admin) {
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
    }
}

function request_is_https(): bool
{
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $trusted = array_filter(array_map('trim', explode(',', getenv('NEMA_TRUSTED_PROXIES') ?: '')));
    return in_array($remote, $trusted, true)
        && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function require_https(): void
{
    if (request_is_https()) {
        return;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'nema.one';
    if (!preg_match('/\A[a-z0-9.-]+(?::\d+)?\z/i', $host)) {
        $host = 'nema.one';
    }
    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/admin/'), true, 308);
    exit;
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('NEMA_ADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf'] ?? '';
    if (!is_string($submitted) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $submitted)) {
        http_response_code(403);
        exit('Die Anfrage konnte nicht verifiziert werden.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('Methode nicht erlaubt.');
    }
}

function audit(string $event, ?int $userId = null, array $context = []): void
{
    try {
        $statement = database()->prepare('INSERT INTO admin_audit_log (admin_user_id, event_type, ip_hash, context_json) VALUES (:user, :event, :ip, :context)');
        $statement->execute([
            'user' => $userId,
            'event' => $event,
            'ip' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            'context' => $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR),
        ]);
    } catch (Throwable $exception) {
        error_log('Audit logging failed: ' . $exception->getMessage());
    }
}

