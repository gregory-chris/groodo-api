<?php
declare(strict_types=1);

return [
    'allowed_origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'https://groodo.greq.me'),
    'allowed_methods' => explode(',', $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS'),
    'allowed_headers' => explode(',', $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type,Authorization,X-Requested-With'),
    'exposed_headers' => [],
    'max_age' => 86400, // 24 hours
    'credentials' => true,
];
