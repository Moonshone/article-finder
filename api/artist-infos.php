<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$artistId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($artistId === false || $artistId === null || $artistId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Künstler-ID.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $databaseConfigFile = __DIR__ . '/../config/database.php';
    $pdo = null;

    if (is_readable($databaseConfigFile)) {
        $databaseConfig = require $databaseConfigFile;

        if ($databaseConfig instanceof PDO) {
            $pdo = $databaseConfig;
        } elseif (is_array($databaseConfig)) {
            $host = $databaseConfig['host'] ?? null;
            $port = $databaseConfig['port'] ?? '3306';
            $user = $databaseConfig['user'] ?? ($databaseConfig['username'] ?? null);
            $password = $databaseConfig['password'] ?? null;
            $database = $databaseConfig['database'] ?? ($databaseConfig['dbname'] ?? 's02u4284_nema_data');
        }
    }

    if (!$pdo instanceof PDO) {
        $host = $host ?? getenv('DB_HOST');
        $port = $port ?? (getenv('DB_PORT') ?: '3306');
        $user = $user ?? getenv('DB_USER');
        $password = $password ?? getenv('DB_PASSWORD');
        $database = $database ?? (getenv('DB_NAME') ?: 's02u4284_nema_data');

        if (!$host || !$user || $password === false || $password === null) {
            throw new RuntimeException('Database credentials are not configured.');
        }

        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('The PDO MySQL driver is not available.');
        }

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    $statement = $pdo->prepare('SELECT description FROM artists WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $artistId]);
    $description = $statement->fetchColumn();

    if ($description === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Künstlerbeschreibung nicht gefunden.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(
        ['description' => (string) $description],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    error_log('artist-infos.php: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Künstlerbeschreibung konnte nicht geladen werden.'], JSON_UNESCAPED_UNICODE);
}
