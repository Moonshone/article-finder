<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/stories.php';
require_once __DIR__ . '/../app/admin.php';
security_headers();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); http_response_code(405); exit('Methode nicht erlaubt.'); }
$isAdmin = false;
if (isset($_COOKIE['NEMA_ADMIN']) && is_string($_COOKIE['NEMA_ADMIN'])) {
    start_secure_session();
    $isAdmin = admin_session_is_valid();
    if ($isAdmin) $_SESSION['last_seen'] = time();
}
$slug = is_string($_GET['slug'] ?? null) && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $_GET['slug']) && strlen($_GET['slug']) <= 191 ? $_GET['slug'] : '';
try {
    $statement = database()->prepare("SELECT id, title, excerpt, content, image_url, published_at FROM stories WHERE slug=:slug AND status='published' AND published_at IS NOT NULL AND published_at <= UTC_TIMESTAMP() LIMIT 1");
    $statement->execute(['slug' => $slug]); $story = $statement->fetch();
} catch (Throwable $exception) { error_log('Public story failed: ' . $exception->getMessage()); $story = false; }
if (!$story) { http_response_code(404); $story = null; }
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="<?= $story ? 'index,follow' : 'noindex' ?>"><title><?= $story ? e(story_plain_text($story['title'])) . ' — NEMA' : 'Story nicht gefunden — NEMA' ?></title><link rel="stylesheet" href="/styles/style.css"><link rel="stylesheet" href="/styles/stories.css"></head><body><div class="page-shell stories-shell"><header class="site-header"><nav class="main-nav" aria-label="Hauptnavigation"><a class="nav-link" href="/">Home</a><a class="nav-link" href="/sites/kunst.html">Kunst</a><a class="nav-link" href="/sites/ai-me.html">AI &amp; Me</a><a class="nav-link active" href="/stories/" aria-current="page">Stories</a></nav></header><main class="story-detail"><?php if (!$story): ?><p class="eyebrow">404</p><h1>Story nicht gefunden</h1><p>Diese Story existiert nicht oder ist nicht veröffentlicht.</p><a href="/stories/">← Alle Stories</a><?php else: ?><?php if ($isAdmin): ?><aside class="story-admin" aria-label="Admin-Funktionen"><strong>Admin:</strong> <a href="/admin/">← Zurück zum Admin-Bereich</a><a href="/admin/edit.php?id=<?= (int) $story['id'] ?>">Story bearbeiten</a></aside><?php endif; ?><a class="story-back" href="/stories/">← Alle Stories</a><time datetime="<?= e(substr($story['published_at'], 0, 10)) ?>"><?= e((new DateTimeImmutable($story['published_at']))->format('d.m.Y')) ?></time><div class="story-title" role="heading" aria-level="1"><?= sanitize_story_html($story['title']) ?></div><?php if ($story['image_url']): ?><img class="story-hero" src="<?= e($story['image_url']) ?>" alt=""><?php endif; ?><div class="story-content"><?= sanitize_story_html($story['content']) ?></div><?php endif; ?></main></div></body></html>
