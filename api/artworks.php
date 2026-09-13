<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/artworks.php';

header('Content-Type: application/json; charset=utf-8');
security_headers();

$artistId = filter_input(
    INPUT_GET,
    'artist_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($artistId === false || $artistId === null) {
    http_response_code(400);
    echo json_encode(
        ['error' => 'Ungültige Künstler-ID.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

try {
    $artworks = load_artworks($artistId);
} catch (Throwable $exception) {
    error_log('Artwork gallery database query failed: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(
        ['error' => 'Derzeit sind keine Werke verfügbar.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

echo json_encode(
    ['artist_id' => $artistId, 'artworks' => $artworks],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
