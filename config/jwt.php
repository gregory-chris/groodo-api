<?php
declare(strict_types=1);

return [
    'secret' => $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-this-in-production',
    'algorithm' => $_ENV['JWT_ALGORITHM'] ?? 'HS256',
    'expiration' => (int)($_ENV['JWT_EXPIRATION'] ?? 604800), // 7 days
    'leeway' => 60, // 1 minute leeway for clock skew
];
