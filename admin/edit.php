<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/admin.php';
require_once __DIR__ . '/../app/stories.php';
boot_admin();
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) { header('Allow: GET, POST'); http_response_code(405); exit('Methode nicht erlaubt.'); }
$id = isset($_GET['id']) ? positive_id($_GET['id']) : null;
if (isset($_GET['id']) && $id === null) { http_response_code(400); exit('Ungültige Story-ID.'); }
$story = ['title' => '', 'excerpt' => '', 'content' => '', 'status' => 'draft', 'image_url' => null];
if ($id !== null) {
    $statement = database()->prepare('SELECT id, title, excerpt, content, status, image_url FROM stories WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $story = $statement->fetch();
    if (!$story) { http_response_code(404); exit('Story nicht gefunden.'); }
}
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    [$errors, $values] = validate_story_input($_POST);
    $story = array_merge($story, $values);
    if (!$errors) {
        $newImage = null;
        try {
            $newImage = upload_story_image($_FILES['image'] ?? []);
            $pdo = database();
            $slug = story_slug($pdo, story_plain_text($values['title']), $id);
            $imageUrl = $newImage ?? $story['image_url'];
            if ($id === null) {
                $sql = 'INSERT INTO stories (title, slug, excerpt, content, image_url, status, published_at) VALUES (:title, :slug, :excerpt, :content, :image, :status, IF(:status_date = \'published\', UTC_TIMESTAMP(), NULL))';
                $statement = $pdo->prepare($sql);
            } else {
                $sql = 'UPDATE stories SET title=:title, slug=:slug, excerpt=:excerpt, content=:content, image_url=:image, status=:status, published_at=CASE WHEN :status_date = \'published\' THEN COALESCE(published_at, UTC_TIMESTAMP()) ELSE NULL END WHERE id=:id';
                $statement = $pdo->prepare($sql);
            }
            $params = ['title' => $values['title'], 'slug' => $slug, 'excerpt' => $values['excerpt'] ?: null, 'content' => $values['content'], 'image' => $imageUrl, 'status' => $values['status'], 'status_date' => $values['status']];
            if ($id !== null) $params['id'] = $id;
            $statement->execute($params);
            $savedId = $id ?? (int) $pdo->lastInsertId();
            if ($newImage && !empty($story['image_url'])) delete_story_image($story['image_url']);
            audit($id === null ? 'story_created' : 'story_updated', $_SESSION['admin_id'], ['story_id' => $savedId, 'status' => $values['status']]);
            redirect_with_message($id === null ? 'Story wurde erstellt.' : 'Story wurde gespeichert.');
        } catch (RuntimeException $exception) {
            if ($newImage) delete_story_image($newImage);
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($newImage) delete_story_image($newImage);
            error_log('Saving story failed: ' . $exception->getMessage());
            $errors[] = 'Die Story konnte nicht gespeichert werden.';
        }
    }
}
?>
<?php
function rich_story_field(string $name, string $label, string $value, int $maxLength, string $height = ''): void { ?>
<div class="editor-field<?= $height ? ' editor-field--' . e($height) : '' ?>" data-rich-editor><span class="editor-label"><?= e($label) ?> <small>Fett, kursiv, Pixelgröße und Ausrichtung</small></span><div class="editor-toolbar" role="toolbar" aria-label="<?= e($label) ?> formatieren"><select class="editor-size" aria-label="Schriftgröße"><option value="" selected>Größe</option><?php foreach (story_font_sizes() as $size): ?><option value="story-font-<?= $size ?>"><?= $size ?> px</option><?php endforeach; ?></select><button type="button" data-inline-tag="strong" aria-label="Fett"><strong>B</strong></button><button type="button" data-inline-tag="em" aria-label="Kursiv"><em>I</em></button><button type="button" data-align="story-align-left">Links</button><button type="button" data-align="story-align-center">Zentriert</button><button type="button" data-align="story-align-right">Rechts</button></div><div class="rich-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="<?= e($label) ?>"><?= sanitize_story_html($value) ?></div><textarea class="editor-source" name="<?= e($name) ?>" maxlength="<?= $maxLength ?>"><?= e($value) ?></textarea></div>
<?php } ?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $id ? 'Story bearbeiten' : 'Story erstellen' ?> — NEMA</title><link rel="stylesheet" href="/styles/style.css"><link rel="stylesheet" href="/styles/admin.css"></head><body class="admin-page"><header class="admin-header"><a href="/admin/">NEMA / STORIES</a></header><main class="admin-shell admin-editor"><a href="/admin/">← Übersicht</a><h1><?= $id ? 'Story bearbeiten' : 'Neue Story' ?></h1><?php if ($errors): ?><div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><?php rich_story_field('title', 'Titel', $story['title'], 200, 'title'); ?><?php rich_story_field('excerpt', 'Kurzbeschreibung', $story['excerpt'], 500, 'excerpt'); ?><?php rich_story_field('content', 'Inhalt', $story['content'], 100000, 'content'); ?><label>Bild (JPEG, PNG oder WebP, max. 5 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><?php if ($story['image_url']): ?><img class="editor-preview" src="<?= e($story['image_url']) ?>" alt="Aktuelles Story-Bild"><?php endif; ?><label>Status<select name="status"><option value="draft"<?= $story['status'] === 'draft' ? ' selected' : '' ?>>Entwurf</option><option value="published"<?= $story['status'] === 'published' ? ' selected' : '' ?>>Veröffentlicht</option></select></label><button class="admin-button" type="submit">Story speichern</button></form></main><script src="/src/admin.js"></script></body></html>
