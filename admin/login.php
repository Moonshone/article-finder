<?php

declare(strict_types=1);
require_once __DIR__ . '/../app/admin.php';
boot_admin(false);
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) { header('Allow: GET, POST'); http_response_code(405); exit('Methode nicht erlaubt.'); }
if (isset($_SESSION['admin_id'], $_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin') {
    header('Location: /admin/'); exit;
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $identity = hash('sha256', 'account|' . strtolower($username) . '|' . $ip);
    $ipIdentity = hash('sha256', 'ip|' . $ip);
    try {
        $pdo = database();
        $count = $pdo->prepare('SELECT identity_hash, COUNT(*) AS failures FROM login_attempts WHERE identity_hash IN (:identity, :ip_identity) AND attempted_at >= (UTC_TIMESTAMP() - INTERVAL 15 MINUTE) GROUP BY identity_hash');
        $count->execute(['identity' => $identity, 'ip_identity' => $ipIdentity]);
        $limited = false;
        foreach ($count->fetchAll() as $attemptGroup) $limited = $limited || (int) $attemptGroup['failures'] >= 10;
        $user = null;
        if (!$limited && $username !== '' && strlen($username) <= 100) {
            $find = $pdo->prepare('SELECT id, password_hash, role, is_active FROM admin_users WHERE username = :username LIMIT 1');
            $find->execute(['username' => $username]);
            $user = $find->fetch() ?: null;
        }
        $dummy = '$2y$12$vXduT0nAECoKaS8rSq4ftOtYZhzXTNKJGwHrMQzk4zQmtq5SW5y6C';
        $valid = !$limited && password_verify($password, is_array($user) ? $user['password_hash'] : $dummy)
            && is_array($user) && (int) $user['is_active'] === 1 && $user['role'] === 'admin';
        if ($valid) {
            $pdo->prepare('DELETE FROM login_attempts WHERE identity_hash IN (:identity, :ip_identity)')->execute(['identity' => $identity, 'ip_identity' => $ipIdentity]);
            session_regenerate_id(true);
            $_SESSION = ['admin_id' => (int) $user['id'], 'admin_role' => 'admin', 'created_at' => time(), 'last_seen' => time(), 'user_agent' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')];
            csrf_token();
            audit('login_success', (int) $user['id']);
            header('Location: /admin/'); exit;
        }
        if (!$limited) $pdo->prepare('INSERT INTO login_attempts (identity_hash) VALUES (:identity), (:ip_identity)')->execute(['identity' => $identity, 'ip_identity' => $ipIdentity]);
        audit($limited ? 'login_rate_limited' : 'login_failed');
        usleep(random_int(250000, 500000));
        $error = $limited ? 'Zu viele Versuche. Bitte in 15 Minuten erneut versuchen.' : 'Anmeldedaten sind nicht korrekt.';
    } catch (Throwable $exception) {
        error_log('Admin login failed: ' . $exception->getMessage());
        $error = 'Die Anmeldung ist momentan nicht möglich.';
    }
}
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login — NEMA</title><link rel="stylesheet" href="/styles/style.css"><link rel="stylesheet" href="/styles/admin.css"></head>
<body class="admin-page"><main class="login-card"><p class="eyebrow">NEMA / STORIES</p><h1>Admin-Anmeldung</h1><?php if ($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?><form method="post" autocomplete="on"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><label>Benutzername<input name="username" maxlength="100" autocomplete="username" required></label><label>Passwort<input type="password" name="password" autocomplete="current-password" required></label><button class="admin-button" type="submit">Sicher anmelden</button></form></main></body></html>
