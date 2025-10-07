#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Clean Test Users Script (Auto Mode)
 * 
 * Automatically deletes all test users from the database without confirmation.
 * This version is designed for automated test workflows.
 * 
 * Usage:
 *   php clean-test-users-auto.php
 *   ./clean-test-users-auto.php (on Unix systems with executable permissions)
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

try {
    // Load environment variables
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    // Database configuration
    $dbPath = __DIR__ . '/../database/groodo-api.sqlite';

    if (!file_exists($dbPath)) {
        echo "Error: Database file not found: {$dbPath}\n";
        echo "Run migration first: php migrate.php\n";
        exit(1);
    }

    // Connect to database
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Define test user email patterns (exact matches)
    $testEmails = [
        'testuser@example.com',
        'test@example.com',
        'invalid@example.com',
        'user@test.com',
        'demo@example.com',
        'newuser@example.com',
        'another@example.com',
    ];

    // Begin transaction
    $pdo->beginTransaction();

    try {
        $deletedCount = 0;

        // Delete users
        $placeholders = array_fill(0, count($testEmails), '?');
        $sql = 'DELETE FROM users WHERE email IN (' . implode(',', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($testEmails);
        $deletedCount = $stmt->rowCount();

        // Commit transaction
        $pdo->commit();

        if ($deletedCount > 0) {
            echo "✓ Deleted {$deletedCount} test user(s)\n";
        } else {
            echo "- No test users found (database already clean)\n";
        }

        exit(0);

    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}
