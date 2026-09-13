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
 * Load every artwork belonging to an artist through the foreign key only.
 *
 * @return list<array{id: int, artist_id: int, name: string, year: string, location: string, url: string|null}>
 */
function load_artworks(int $artistId, ?PDO $connection = null, ?callable $fileExists = null): array
{
    if ($artistId < 1) {
        throw new InvalidArgumentException('Artist ID must be a positive integer.');
    }

    $connection ??= database();
    $statement = $connection->prepare(
        'SELECT id, artist_id, Name, Year, location, url
         FROM art_works
         WHERE artist_id = :artist_id
         ORDER BY id ASC'
    );
    $statement->bindValue(':artist_id', $artistId, PDO::PARAM_INT);
    $statement->execute();

    $artworks = [];
    foreach ($statement->fetchAll() as $artwork) {
        $id = filter_var($artwork['id'] ?? null, FILTER_VALIDATE_INT);
        $rowArtistId = filter_var($artwork['artist_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $rowArtistId === false || $rowArtistId !== $artistId) {
            continue;
        }

        $url = isset($artwork['url']) && is_string($artwork['url'])
            ? artwork_image_url($artwork['url'], $fileExists)
            : null;
        $artworks[] = [
            'id' => $id,
            'artist_id' => $rowArtistId,
            'name' => (string) ($artwork['Name'] ?? ''),
            'year' => (string) ($artwork['Year'] ?? ''),
            'location' => (string) ($artwork['location'] ?? ''),
            'url' => $url,
        ];
    }

    return $artworks;
}

/** @return list<string> */
function load_artwork_images(int $artistId, ?PDO $connection = null, ?callable $fileExists = null): array
{
    return array_values(array_map(
        static fn (array $artwork): string => $artwork['url'],
        array_filter(
            load_artworks($artistId, $connection, $fileExists),
            static fn (array $artwork): bool => $artwork['url'] !== null
        )
    ));
}
