<?php

declare(strict_types=1);

// DELETE THIS FILE AFTER INITIAL ADMIN SETUP.

require_once __DIR__ . '/../app/admin.php';

boot_admin(false);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$errors = [];
$username = '';
$created = $method === 'GET' && ($_SESSION['setup_admin_created'] ?? false) === true;
unset($_SESSION['setup_admin_created']);

try {
    $pdo = database();
    $findAdministrator = $pdo->prepare("SELECT id\nFROM admin_users\nWHERE role = 'admin'\nAND is_active = 1\nLIMIT 1");
    $findAdministrator->execute();
    $setupDisabled = $findAdministrator->fetchColumn() !== false;
} catch (Throwable $exception) {
    error_log('Initial administrator check failed: ' . $exception->getMessage());
    $setupDisabled = true;
    $errors[] = 'Administrator setup is currently unavailable.';
}

if ($method === 'POST') {
    if ($setupDisabled) {
        $errors = [];
    } else {
        verify_csrf();

        $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $passwordConfirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';

        $usernameLength = preg_match_all('/./us', $username, $matches);
        $passwordLength = preg_match_all('/./us', $password, $matches);
        if ($username === '') {
            $errors[] = 'Benutzername ist erforderlich.';
        } elseif ($usernameLength === false || $usernameLength > 100) {
            $errors[] = 'Benutzername darf maximal 100 Zeichen lang sein.';
        }
        if ($password === '') {
            $errors[] = 'Passwort ist erforderlich.';
        } elseif ($passwordLength === false || $passwordLength < 14) {
            $errors[] = 'Passwort muss mindestens 14 Zeichen lang sein.';
        }
        if ($password !== $passwordConfirmation) {
            $errors[] = 'Die Passwörter stimmen nicht überein.';
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();
                $lockedCheck = $pdo->prepare("SELECT id\nFROM admin_users\nWHERE role = 'admin'\nAND is_active = 1\nLIMIT 1\nFOR UPDATE");
                $lockedCheck->execute();

                if ($lockedCheck->fetchColumn() !== false) {
                    $pdo->rollBack();
                    $setupDisabled = true;
                } else {
                    $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                    $hash = password_hash($password, $algorithm);
                    if (!is_string($hash)) {
                        throw new RuntimeException('Password hashing failed.');
                    }

                    $insert = $pdo->prepare("INSERT INTO admin_users\n(username, password_hash, role, is_active)\nVALUES (:username, :hash, 'admin', 1)");
                    $insert->execute(['username' => $username, 'hash' => $hash]);
                    $pdo->commit();

                    unset($_SESSION['csrf']);
                    $_SESSION['setup_admin_created'] = true;
                    header('Location: /admin/setup-admin.php', true, 303);
                    exit;
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Initial administrator creation failed: ' . $exception->getMessage());
                $errors[] = 'Administrator could not be created.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Administrator einrichten — NEMA</title>
    <link rel="stylesheet" href="/styles/style.css">
    <link rel="stylesheet" href="/styles/admin.css">
</head>
<body class="admin-page">
<main class="login-card">
    <p class="eyebrow">NEMA / SETUP</p>
    <h1>Administrator einrichten</h1>
    <?php if ($created): ?>
        <p class="alert" role="status">Administrator created successfully.</p>
        <p><a href="/admin/login.php">Zur Admin-Anmeldung</a></p>
    <?php elseif ($setupDisabled): ?>
        <p class="alert" role="status">Administrator setup is disabled.</p>
    <?php else: ?>
        <?php if ($errors !== []): ?>
            <div class="alert alert--error" role="alert"><ul>
                <?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <label>Benutzername
                <input name="username" maxlength="100" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required>
            </label>
            <label>Passwort
                <input type="password" name="password" minlength="14" autocomplete="new-password" required>
            </label>
            <label>Passwort wiederholen
                <input type="password" name="password_confirmation" minlength="14" autocomplete="new-password" required>
            </label>
            <button class="admin-button" type="submit">Administrator erstellen</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
