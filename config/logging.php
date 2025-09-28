<?php
declare(strict_types=1);

return [
    'level' => $_ENV['LOG_LEVEL'] ?? 'DEBUG',
    'path' => $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs/groodo-api.log',
    'max_files' => (int)($_ENV['LOG_MAX_FILES'] ?? 30),
    'format' => '[%datetime%] %channel%.%level_name%: %message% %context% %extra%' . PHP_EOL,
    'date_format' => 'Y-m-d H:i:s',
    'channels' => [
        'app' => 'application',
        'db' => 'database',
        'auth' => 'authentication',
        'email' => 'email',
        'security' => 'security',
    ],
];
