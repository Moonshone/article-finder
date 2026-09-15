<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const IDLE_TIMEOUT = 1800;
const ABSOLUTE_TIMEOUT = 28800;

function boot_admin(bool $authenticationRequired = true): void
{
    require_https();
    security_headers(true);
    start_secure_session();

    if (!$authenticationRequired) {
        return;
    }
    $valid = admin_session_is_valid();
    if (!$valid) {
        destroy_admin_session();
        header('Location: /admin/login.php');
        exit;
    }
    $_SESSION['last_seen'] = time();
}

/** Validate an existing admin session without granting or replacing authentication. */
function admin_session_is_valid(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) return false;
    $now = time();
    $structurallyValid = isset($_SESSION['admin_id'], $_SESSION['admin_role'], $_SESSION['created_at'], $_SESSION['last_seen'])
        && is_int($_SESSION['admin_id'])
        && is_int($_SESSION['created_at'])
        && is_int($_SESSION['last_seen'])
        && $_SESSION['admin_role'] === 'admin'
        && isset($_SESSION['user_agent'])
        && is_string($_SESSION['user_agent'])
        && hash_equals($_SESSION['user_agent'], hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''))
        && $_SESSION['last_seen'] <= $now
        && $_SESSION['created_at'] <= $now
        && $now - $_SESSION['last_seen'] <= IDLE_TIMEOUT
        && $now - $_SESSION['created_at'] <= ABSOLUTE_TIMEOUT;
    if (!$structurallyValid) return false;

    // A role change or account deactivation must revoke an existing session too.
    try {
        $statement = database()->prepare('SELECT role, is_active FROM admin_users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $_SESSION['admin_id']]);
        $user = $statement->fetch();
        return is_array($user) && $user['role'] === 'admin' && (int) $user['is_active'] === 1;
    } catch (Throwable $exception) {
        error_log('Admin session validation failed: ' . $exception->getMessage());
        return false;
    }
}

/**
 * Validate the existing admin session for an otherwise public request.
 *
 * Visitors without an admin cookie do not get a new session. This helper does
 * not protect a route; admin pages must continue to use boot_admin().
 */
function public_request_has_valid_admin_session(): bool
{
    if (!isset($_COOKIE['NEMA_ADMIN']) || !is_string($_COOKIE['NEMA_ADMIN'])) {
        return false;
    }

    start_secure_session();
    if (!admin_session_is_valid()) {
        return false;
    }

    $_SESSION['last_seen'] = time();
    return true;
}

function destroy_admin_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function positive_id(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

function redirect_with_message(string $message): never
{
    $_SESSION['flash'] = $message;
    header('Location: /admin/');
    exit;
}
