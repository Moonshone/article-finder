<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/admin.php';
require_once __DIR__ . '/../app/stories.php';
boot_admin();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); http_response_code(405); exit('Methode nicht erlaubt.'); }
try {
    $stories = database()->query('SELECT id, title, slug, status, created_at, updated_at FROM stories ORDER BY created_at DESC')->fetchAll();
} catch (Throwable $exception) {
    error_log('Admin story list failed: ' . $exception->getMessage());
    $stories = [];
    $_SESSION['flash'] = 'Stories konnten nicht geladen werden.';
}
$flash = isset($_SESSION['flash']) && is_string($_SESSION['flash']) ? $_SESSION['flash'] : '';
unset($_SESSION['flash']);
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Stories verwalten — NEMA</title><link rel="stylesheet" href="/styles/style.css"><link rel="stylesheet" href="/styles/admin.css"></head><body class="admin-page">
<header class="admin-header"><a href="/admin/">NEMA / STORIES</a><form method="post" action="/admin/logout.php"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="text-button">Abmelden</button></form></header>
<main class="admin-shell"><div class="admin-title"><div><p class="eyebrow">Verwaltung</p><h1>Stories</h1></div><a class="admin-button" href="/admin/edit.php">Neue Story</a></div>
<?php if ($flash): ?><p class="alert" role="status"><?= e($flash) ?></p><?php endif; ?>
<div class="admin-table-wrap"><table><thead><tr><th>Titel</th><th>Status</th><th>Erstellt</th><th>Aktualisiert</th><th>Aktionen</th></tr></thead><tbody>
<?php if (!$stories): ?><tr><td colspan="5">Noch keine Stories vorhanden.</td></tr><?php endif; ?>
<?php foreach ($stories as $story): ?><tr><td><?= e(story_plain_text($story['title'])) ?></td><td><span class="status status--<?= e($story['status']) ?>"><?= $story['status'] === 'published' ? 'Veröffentlicht' : 'Entwurf' ?></span></td><td><?= e($story['created_at']) ?></td><td><?= e($story['updated_at']) ?></td><td><div class="actions"><?php if ($story['status'] === 'published'): ?><a href="/stories/story.php?slug=<?= rawurlencode($story['slug']) ?>">Story ansehen</a><?php endif; ?><a href="/admin/edit.php?id=<?= (int) $story['id'] ?>">Bearbeiten</a><form method="post" action="/admin/action.php"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $story['id'] ?>"><input type="hidden" name="action" value="<?= $story['status'] === 'published' ? 'unpublish' : 'publish' ?>"><button class="text-button"><?= $story['status'] === 'published' ? 'Zurückziehen' : 'Veröffentlichen' ?></button></form><form method="post" class="delete-form" action="/admin/action.php"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $story['id'] ?>"><input type="hidden" name="action" value="delete"><button class="text-button danger">Löschen</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div></main><script src="/src/admin.js"></script></body></html>
