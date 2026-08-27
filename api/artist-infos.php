<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

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

    // Datenbankverbindung laden
    require_once __DIR__ . '/../../config.php';

    // Nur description auslesen
    $statement = $pdo->prepare(
        'SELECT description
         FROM artists
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute([
        'id' => $artistId
    ]);

    $description = $statement->fetchColumn();

    if ($description === false) {
        http_response_code(404);

        echo json_encode(
            ['error' => 'Künstlerbeschreibung nicht gefunden.'],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    echo json_encode(
        [
            'description' => (string) $description
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
