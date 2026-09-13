<?php

declare(strict_types=1);

/**
 * Validate a database-provided artwork URL against the local artwork directory.
 *
 * Remote URLs, SVG files and paths outside the selected artist's directory are
 * deliberately unsupported. The returned value is safe to JSON-encode and use
 * as an image URL after the browser's normal attribute handling.
 */
function artwork_image_url(string $artistSlug, ?string $value, ?callable $fileExists = null): ?string
{
    $path = trim($value ?? '');
    if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '//')) {
        return null;
    }

    if (!preg_match('/\A[a-z0-9-]+\z/', $artistSlug) || str_contains($path, '?') || str_contains($path, '#')) {
        return null;
    }

    $relativePath = ltrim($path, '/');
    $decodedPath = $relativePath;
    for ($iteration = 0; $iteration < 3; $iteration++) {
        $decoded = rawurldecode($decodedPath);
        if ($decoded === $decodedPath) {
            break;
        }
        $decodedPath = $decoded;
    }

    $expectedPrefix = 'bilder/artworks/' . $artistSlug . '/';
    if (!str_starts_with($decodedPath, $expectedPrefix)) {
        return null;
    }

    foreach (explode('/', $decodedPath) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }
    }

    if (!preg_match('/\.(?:avif|gif|jpe?g|png|webp)\z/i', $decodedPath)) {
        return null;
    }

    $fileExists ??= static fn (string $file): bool => is_file(NEMA_ROOT . '/' . $file);
    if (!$fileExists($decodedPath)) {
        return null;
    }

    return '/' . $relativePath;
}

/**
 * @return list<string>
 */
function load_artwork_images(int $artistId, string $artistSlug, ?PDO $connection = null, ?callable $fileExists = null): array
{
    $connection ??= database();
    $statement = $connection->prepare(
        'SELECT id, artist, url FROM art_works WHERE artist = :artist ORDER BY id ASC'
    );
    $statement->bindValue(':artist', $artistId, PDO::PARAM_INT);
    $statement->execute();

    $images = [];
    foreach ($statement->fetchAll() as $artwork) {
        $rowArtistId = filter_var($artwork['artist'] ?? null, FILTER_VALIDATE_INT);
        if ($rowArtistId === false || $rowArtistId !== $artistId) {
            continue;
        }

        $url = isset($artwork['url']) && is_string($artwork['url'])
            ? artwork_image_url($artistSlug, $artwork['url'], $fileExists)
            : null;
        if ($url !== null) {
            $images[] = $url;
        }
    }

    return $images;
}
