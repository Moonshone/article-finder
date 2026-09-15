<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function story_slug(PDO $pdo, string $title, ?int $excludeId = null): string
{
    $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: 'story';
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($base)) ?? '', '-');
    $base = substr($base !== '' ? $base : 'story', 0, 180);
    for ($suffix = 1; $suffix <= 1000; $suffix++) {
        $slug = $suffix === 1 ? $base : substr($base, 0, 180 - strlen((string) $suffix) - 1) . '-' . $suffix;
        $sql = 'SELECT id FROM stories WHERE slug = :slug' . ($excludeId === null ? '' : ' AND id <> :id') . ' LIMIT 1';
        $statement = $pdo->prepare($sql);
        $params = ['slug' => $slug];
        if ($excludeId !== null) $params['id'] = $excludeId;
        $statement->execute($params);
        if (!$statement->fetch()) return $slug;
    }
    throw new RuntimeException('Unable to create unique slug.');
}

function sanitize_story_html(string $html): string
{
    if (!class_exists(DOMDocument::class)) {
        return '<p>' . nl2br(e(strip_tags($html)), false) . '</p>';
    }
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><div id="story-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $allowed = ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li', 'span'];
    $fontClasses = array_map(static fn (int $size): string => 'story-font-' . $size, story_font_sizes());
    $alignClasses = ['story-align-left', 'story-align-center', 'story-align-right'];
    $root = $document->getElementById('story-root');
    if (!$root) return '';
    $walk = function (DOMNode $node) use (&$walk, $allowed, $fontClasses, $alignClasses): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                $walk($child);
                if (!in_array($tag, $allowed, true)) {
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                $originalHref = $tag === 'a' ? trim($child->getAttribute('href')) : '';
                $classAllowlist = $tag === 'span'
                    ? $fontClasses
                    : (in_array($tag, ['p', 'li'], true) ? $alignClasses : []);
                $originalClasses = $classAllowlist
                    ? (preg_split('/\s+/', trim($child->getAttribute('class'))) ?: [])
                    : [];
                foreach (iterator_to_array($child->attributes) as $attribute) $child->removeAttribute($attribute->name);
                $safeClasses = array_values(array_unique(array_intersect($originalClasses, $classAllowlist)));
                if ($safeClasses) $child->setAttribute('class', implode(' ', $safeClasses));
                if ($tag === 'a') {
                    $href = $originalHref;
                    if (preg_match('#\Ahttps?://#i', $href) && filter_var($href, FILTER_VALIDATE_URL)) {
                        $child->setAttribute('href', $href);
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                }
            }
        }
    };
    $walk($root);
    $result = '';
    foreach ($root->childNodes as $child) $result .= $document->saveHTML($child);
    return trim($result);
}

/** @return list<int> */
function story_font_sizes(): array
{
    return [12, 14, 16, 18, 20, 22, 24, 26, 28, 30, 32, 36, 40, 44, 48];
}

function story_plain_text(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

function validate_story_input(array $input): array
{
    $title = trim(is_string($input['title'] ?? null) ? $input['title'] : '');
    $excerpt = trim(is_string($input['excerpt'] ?? null) ? $input['excerpt'] : '');
    $content = is_string($input['content'] ?? null) ? trim($input['content']) : '';
    $status = is_string($input['status'] ?? null) ? $input['status'] : '';
    $safeTitle = sanitize_story_html($title);
    $safeExcerpt = sanitize_story_html($excerpt);
    $safeContent = sanitize_story_html($content);
    $errors = [];
    if (story_plain_text($title) === '' || mb_strlen(story_plain_text($title)) > 200 || mb_strlen($safeTitle) > 200) $errors[] = 'Der Titel einschließlich Formatierung darf höchstens 200 Zeichen lang sein.';
    if (mb_strlen(story_plain_text($excerpt)) > 500 || mb_strlen($safeExcerpt) > 500) $errors[] = 'Die Kurzbeschreibung einschließlich Formatierung darf höchstens 500 Zeichen lang sein.';
    if (story_plain_text($content) === '' || mb_strlen($content) > 100000) $errors[] = 'Der Inhalt muss zwischen 1 und 100.000 Zeichen lang sein.';
    if (!in_array($status, ['draft', 'published'], true)) $errors[] = 'Der Status ist ungültig.';
    return [$errors, ['title' => $safeTitle, 'excerpt' => $safeExcerpt, 'content' => $safeContent, 'status' => $status]];
}

function upload_story_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !isset($file['tmp_name'], $file['size']) || !is_uploaded_file($file['tmp_name'])) throw new RuntimeException('Bild-Upload fehlgeschlagen.');
    if ((int) $file['size'] > 5 * 1024 * 1024) throw new RuntimeException('Das Bild darf höchstens 5 MB groß sein.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($types[$mime]) || @getimagesize($file['tmp_name']) === false) throw new RuntimeException('Nur gültige JPEG-, PNG- oder WebP-Bilder sind erlaubt.');
    if (!is_dir(NEMA_UPLOAD_DIR) && !mkdir(NEMA_UPLOAD_DIR, 0750, true) && !is_dir(NEMA_UPLOAD_DIR)) throw new RuntimeException('Upload-Verzeichnis nicht verfügbar.');
    $name = bin2hex(random_bytes(24)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], NEMA_UPLOAD_DIR . '/' . $name)) throw new RuntimeException('Bild konnte nicht gespeichert werden.');
    return NEMA_UPLOAD_URL . $name;
}

function delete_story_image(?string $url): void
{
    if ($url === null || !preg_match('#\A/uploads/stories/([a-f0-9]{48}\.(?:jpg|png|webp))\z#', $url, $match)) return;
    $path = NEMA_UPLOAD_DIR . '/' . $match[1];
    if (is_file($path)) @unlink($path);
}
