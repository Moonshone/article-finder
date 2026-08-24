<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$artists = [
    'laleh' => 'laleh',
    'hassan' => 'hassan',
    'shabrokh' => 'shabrokh',
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

$directory = __DIR__ . '/../bilder/artworks/' . $artists[$artist];
$images = [];

if (is_dir($directory) && is_readable($directory)) {
    $files = scandir($directory);

    if ($files !== false) {
        foreach ($files as $file) {
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

            if (is_file($path) && in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $images[] = $file;
            }
        }
    }
}

natcasesort($images);
$images = array_values(array_map(
    static fn (string $file): string => '/bilder/artworks/' . $artists[$artist] . '/' . rawurlencode($file),
    $images
));

echo json_encode(
    ['artist' => $artist, 'images' => $images],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
