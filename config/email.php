<?php
declare(strict_types=1);

return [
    'smtp' => [
        'host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
        'port' => (int)($_ENV['SMTP_PORT'] ?? 587),
        'username' => $_ENV['SMTP_USERNAME'] ?? 'sgchris@gmail.com',
        'password' => $_ENV['SMTP_PASSWORD'] ?? 'your-app-password',
        'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls',
    ],
    'from' => [
        'email' => $_ENV['SMTP_FROM_EMAIL'] ?? 'sgchris@gmail.com',
        'name' => $_ENV['SMTP_FROM_NAME'] ?? 'GrooDo API',
    ],
    'templates' => [
        'email_confirmation' => __DIR__ . '/../templates/email_confirmation.html',
        'password_reset' => __DIR__ . '/../templates/password_reset.html',
    ],
];
