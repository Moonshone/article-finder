<?php

declare(strict_types=1);

require_once __DIR__ . '/images.php';

/**
 * Validate a database-provided artwork URL with the shared image URL policy.
 */
function artwork_image_url(?string $value, ?callable $fileExists = null): ?string
{
    return database_image_url($value, $fileExists);
}

/**
 * @return list<string>
 */
function load_artwork_images(int $artistId, ?PDO $connection = null, ?callable $fileExists = null): array
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
            ? artwork_image_url($artwork['url'], $fileExists)
            : null;
        if ($url !== null) {
            $images[] = $url;
        }
    }

    return $images;
}
