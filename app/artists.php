<?php

declare(strict_types=1);

require_once __DIR__ . '/images.php';

/**
 * Return a browser-safe, existing local artist image URL or null.
 *
 * The database is the only source; validation and local file checks are
 * delegated to the shared image URL policy.
 */
function artist_image_url(?string $value, ?callable $fileExists = null): ?string
{
    return database_image_url($value, $fileExists);
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
