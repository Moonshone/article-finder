<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';
$username = trim(getenv('NEMA_ADMIN_USERNAME') ?: '');
$password = getenv('NEMA_ADMIN_PASSWORD') ?: '';
if ($username === '' || strlen($username) > 100 || strlen($password) < 14) {
    fwrite(STDERR, "Set NEMA_ADMIN_USERNAME and NEMA_ADMIN_PASSWORD (minimum 14 characters).\n");
    exit(1);
}
$algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
$statement = database()->prepare('INSERT INTO admin_users (username, password_hash, role) VALUES (:username, :hash, \'admin\') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = \'admin\', is_active = 1');
$statement->execute(['username' => $username, 'hash' => password_hash($password, $algorithm)]);
fwrite(STDOUT, "Administrator created or updated.\n");

