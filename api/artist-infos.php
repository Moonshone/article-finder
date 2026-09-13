<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/artists.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
security_headers();

$artistId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($artistId === false || $artistId === null || $artistId < 1) {
    http_response_code(400);

    echo json_encode(
        ['error' => 'Ungültige Künstler-ID.'],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

try {

    $statement = database()->prepare(
        'SELECT description, img_url
         FROM artists
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute([
        'id' => $artistId
    ]);

    $artist = $statement->fetch();

    if ($artist === false) {
        http_response_code(404);

        echo json_encode(
            ['error' => 'Künstlerbeschreibung nicht gefunden.'],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    echo json_encode(
        [
            'description' => (string) ($artist['description'] ?? ''),
            'image_url' => artist_image_url(
                isset($artist['img_url']) && is_string($artist['img_url']) ? $artist['img_url'] : null
            ),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

} catch (Throwable $exception) {

    error_log(
        'artist-infos.php: ' . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'error' => 'Künstlerbeschreibung konnte nicht geladen werden.'
        ],
        JSON_UNESCAPED_UNICODE
    );
}
