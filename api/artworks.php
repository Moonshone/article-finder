<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/artworks.php';

header('Content-Type: application/json; charset=utf-8');
security_headers();

$artistId = filter_input(INPUT_GET, 'artist', FILTER_VALIDATE_INT);

if ($artistId === false || $artistId === null || $artistId < 1) {
    http_response_code(400);
    echo json_encode(
        ['error' => 'Ungültige Künstler-ID.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

try {
    $images = load_artwork_images($artistId);
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
    ['artist' => $artistId, 'images' => $images],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
