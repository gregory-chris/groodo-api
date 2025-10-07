#!/usr/bin/env php
<?php
/**
 * Quick Email Confirmation Script
 * Confirms email for testuser@example.com (non-interactive, for automated tests)
 */

require __DIR__ . '/../../vendor/autoload.php';
use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
    
    $dbPath = __DIR__ . '/../../database/groodo-api.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare('UPDATE users SET is_email_confirmed = 1, email_confirmation_token = NULL, updated_at = ? WHERE email = ?');
    $stmt->execute([gmdate('Y-m-d\TH:i:s\Z'), 'testuser@example.com']);
    
    exit(0);
} catch (Exception $e) {
    exit(1);
}
