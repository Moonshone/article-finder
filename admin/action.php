<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/admin.php';
require_once __DIR__ . '/../app/stories.php';
boot_admin(); require_post(); verify_csrf();
$id = positive_id($_POST['id'] ?? null);
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
if ($id === null || !in_array($action, ['publish', 'unpublish', 'delete'], true)) { http_response_code(400); exit('Ungültige Anfrage.'); }
try {
    $pdo = database();
    if ($action === 'delete') {
        $pdo->beginTransaction();
        $find = $pdo->prepare('SELECT image_url FROM stories WHERE id=:id FOR UPDATE'); $find->execute(['id' => $id]); $image = $find->fetchColumn();
        if ($image === false) { $pdo->rollBack(); http_response_code(404); exit('Story nicht gefunden.'); }
        $delete = $pdo->prepare('DELETE FROM stories WHERE id=:id'); $delete->execute(['id' => $id]); $pdo->commit();
        delete_story_image(is_string($image) ? $image : null); audit('story_deleted', $_SESSION['admin_id'], ['story_id' => $id]); redirect_with_message('Story wurde gelöscht.');
    }
    $status = $action === 'publish' ? 'published' : 'draft';
    $statement = $pdo->prepare('UPDATE stories SET status=:status, published_at=CASE WHEN :date_status = \'published\' THEN COALESCE(published_at, UTC_TIMESTAMP()) ELSE NULL END WHERE id=:id');
    $statement->execute(['status' => $status, 'date_status' => $status, 'id' => $id]);
    audit('story_' . ($action === 'publish' ? 'published' : 'unpublished'), $_SESSION['admin_id'], ['story_id' => $id]); redirect_with_message($action === 'publish' ? 'Story wurde veröffentlicht.' : 'Story wurde zurückgezogen.');
} catch (Throwable $exception) { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); error_log('Story action failed: ' . $exception->getMessage()); redirect_with_message('Aktion konnte nicht ausgeführt werden.'); }
