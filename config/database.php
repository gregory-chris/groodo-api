<?php
declare(strict_types=1);

$dbPath = $_ENV['DB_PATH'] ?? 'database/groodo-api.sqlite';

// Make path absolute if it's relative
if (!str_starts_with($dbPath, '/')) {
    $dbPath = __DIR__ . '/../' . $dbPath;
}

return [
    'driver' => 'sqlite',
    'database' => $dbPath,
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ],
];
