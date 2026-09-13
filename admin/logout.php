<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/admin.php';
boot_admin();
require_post();
verify_csrf();
$id = $_SESSION['admin_id'];
audit('logout', $id);
destroy_admin_session();
header('Location: /admin/login.php');
exit;

