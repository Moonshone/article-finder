<?php

declare(strict_types=1);

/**
 * Return a browser-safe, existing local artist image URL or null.
 *
 * Artist images are intentionally limited to the two image directories used by
 * the site. Remote URLs and browser-interpreted schemes are not supported.
 */
function artist_image_url(?string $value, ?callable $fileExists = null): ?string
{
    $path = trim($value ?? '');
    if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '//')) {
        return null;
    }

    $path = ltrim($path, '/');
    if (str_contains($path, '?') || str_contains($path, '#')) {
        return null;
    }

    $decodedPath = rawurldecode($path);
    if ($decodedPath !== $path && str_contains($decodedPath, '%')) {
        $decodedPath = rawurldecode($decodedPath);
    }

    if (!preg_match('~\A(?:bilder/artists|assets/images/artists)/~', $decodedPath)) {
        return null;
    }

    foreach (explode('/', $decodedPath) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }
    }

    if (!preg_match('~\.(?:avif|gif|jpe?g|png|webp)\z~i', $decodedPath)) {
        return null;
    }

    $fileExists ??= static fn (string $file): bool => is_file(NEMA_ROOT . '/' . $file);
    if (!$fileExists($decodedPath)) {
        return null;
    }

    return '/' . $path;
}

/** @param list<int> $artistIds
 *  @return array<int, string|null>
 */
function load_artist_images(array $artistIds): array
{
    if ($artistIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($artistIds), '?'));
    $statement = database()->prepare(
        "SELECT id, img_url FROM artists WHERE id IN ($placeholders)"
    );
    $statement->execute($artistIds);

    $images = [];
    foreach ($statement->fetchAll() as $artist) {
        $id = filter_var($artist['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && in_array($id, $artistIds, true)) {
            $images[$id] = isset($artist['img_url']) && is_string($artist['img_url'])
                ? artist_image_url($artist['img_url'])
                : null;
        }
    }

    return $images;
}
