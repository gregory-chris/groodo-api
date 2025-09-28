<?php
declare(strict_types=1);

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables for testing
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Override environment variables for testing
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_PATH'] = __DIR__ . '/database/test.sqlite';
$_ENV['JWT_SECRET'] = 'test-jwt-secret-key-for-testing';
$_ENV['LOG_LEVEL'] = 'ERROR';
$_ENV['LOG_PATH'] = __DIR__ . '/logs/test.log';

// Ensure test database directory exists
$testDbDir = dirname($_ENV['DB_PATH']);
if (!is_dir($testDbDir)) {
    mkdir($testDbDir, 0755, true);
}

// Ensure test logs directory exists
$testLogDir = dirname($_ENV['LOG_PATH']);
if (!is_dir($testLogDir)) {
    mkdir($testLogDir, 0755, true);
}
