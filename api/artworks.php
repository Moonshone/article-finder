<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/artworks.php';

header('Content-Type: application/json; charset=utf-8');
security_headers();

$artists = [
    'laleh' => 2,
    'hassan' => 3,
    'shabrokh' => 4,
];

$artist = isset($_GET['artist']) && is_string($_GET['artist']) ? $_GET['artist'] : '';

if (!array_key_exists($artist, $artists)) {
    http_response_code(400);
    echo json_encode(
        ['error' => 'Ungültiger Künstler. Erlaubt sind: laleh, hassan, shabrokh.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

try {
    $images = load_artwork_images($artists[$artist], $artist);
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
    ['artist' => $artist, 'images' => $images],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
