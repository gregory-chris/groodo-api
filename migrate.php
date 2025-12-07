<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Utils\Database;
use App\Utils\Migration;
use App\Services\LoggingService;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Create logger
    $logger = LoggingService::createLogger();
    
    // Create database connection
    $database = new Database($logger);
    
    // Create migration instance
    $migration = new Migration($database, $logger);
    
    // Check command line arguments
    $command = $argv[1] ?? 'create';
    
    switch ($command) {
        case 'create':
            echo "Creating database tables...\n";
            $migration->createTables();
            // Update tasks table to allow NULL dates if needed
            echo "Updating tasks table to allow NULL dates...\n";
            $migration->updateTasksTableAllowNullDate();
            echo "Database tables created successfully!\n";
            break;
            
        case 'drop':
            echo "Dropping database tables...\n";
            $migration->dropTables();
            echo "Database tables dropped successfully!\n";
            break;
            
        case 'reset':
            echo "Resetting database...\n";
            $migration->resetDatabase();
            echo "Database reset successfully!\n";
            break;
            
        case 'update-date-null':
            echo "Updating tasks table to allow NULL dates...\n";
            $migration->updateTasksTableAllowNullDate();
            echo "Tasks table updated successfully!\n";
            break;
            
        case 'add-sessions':
            echo "Adding user_sessions table for multi-session support...\n";
            $migration->addUserSessionsMigration();
            echo "user_sessions table created successfully!\n";
            echo "\nNote: Existing users will need to sign in again to create sessions.\n";
            break;
            
        default:
            echo "Usage: php migrate.php [create|drop|reset|update-date-null|add-sessions]\n";
            echo "  create          - Create database tables (default)\n";
            echo "  drop            - Drop all tables\n";
            echo "  reset           - Drop and recreate all tables\n";
            echo "  update-date-null - Update tasks table to allow NULL dates\n";
            echo "  add-sessions    - Add user_sessions table for multi-session support\n";
            exit(1);
    }
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
