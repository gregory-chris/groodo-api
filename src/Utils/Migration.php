<?php
declare(strict_types=1);

namespace App\Utils;

use PDO;
use Psr\Log\LoggerInterface;

class Migration
{
    private Database $database;
    private LoggerInterface $logger;

    public function __construct(Database $database, LoggerInterface $logger)
    {
        $this->database = $database;
        $this->logger = $logger;
    }

    public function createTables(): void
    {
        $this->logger->info('Starting database migration - creating tables');

        try {
            $this->database->beginTransaction();

            // Create users table
            $this->createUsersTable();
            
            // Create projects table
            $this->createProjectsTable();
            
            // Check if tasks table already exists
            $tasksTableExists = $this->tableExists('tasks');
            
            if ($tasksTableExists) {
                // Update existing tasks table to add project_id and parent_id if missing
                // Use private method since we're already in a transaction
                $this->updateTasksTableColumns();
            } else {
                // Create tasks table (will include project_id and parent_id)
                $this->createTasksTable();
            }
            
            // Create indexes (will check for column existence)
            $this->createIndexes();

            $this->database->commit();
            $this->logger->info('Database migration completed successfully');
        } catch (\Exception $e) {
            $this->database->rollback();
            $this->logger->error('Database migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function createUsersTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                full_name TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                is_email_confirmed INTEGER DEFAULT 0,
                auth_token TEXT,
                auth_expires_at TEXT,
                email_confirmation_token TEXT,
                password_reset_token TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ";

        $this->database->query($sql);
        $this->logger->info('Created users table');
    }

    private function createProjectsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                url TEXT,
                github_url TEXT,
                color TEXT,
                custom_fields TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ";

        $this->database->query($sql);
        $this->logger->info('Created projects table');
    }

    private function createTasksTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                date TEXT,
                order_index INTEGER NOT NULL,
                completed INTEGER DEFAULT 0,
                project_id INTEGER,
                parent_id INTEGER,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES tasks (id) ON DELETE CASCADE
            )
        ";

        $this->database->query($sql);
        $this->logger->info('Created tasks table');
    }

    public function updateTasksTableForProjects(): void
    {
        $this->logger->info('Updating tasks table for projects support');

        try {
            $this->database->beginTransaction();
            $this->updateTasksTableColumns();
            $this->database->commit();
            $this->logger->info('Tasks table updated successfully for projects support');
        } catch (\Exception $e) {
            $this->database->rollback();
            $this->logger->error('Failed to update tasks table', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update tasks table columns without transaction management
     * Called from createTables() which already manages transactions
     */
    private function updateTasksTableColumns(): void
    {
        // Check if columns already exist
        $checkColumns = $this->database->query("PRAGMA table_info(tasks)");
        $columns = $checkColumns->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');

        if (!in_array('project_id', $columnNames)) {
            $this->database->query("ALTER TABLE tasks ADD COLUMN project_id INTEGER");
            $this->logger->info('Added project_id column to tasks table');
        }

        if (!in_array('parent_id', $columnNames)) {
            $this->database->query("ALTER TABLE tasks ADD COLUMN parent_id INTEGER");
            $this->logger->info('Added parent_id column to tasks table');
        }

        // Note: SQLite doesn't support ALTER COLUMN to change NOT NULL to NULL
        // The date column constraint will be handled at the application level
        // For existing databases, NULL dates will work in most SQLite versions
        // New databases will have date as nullable from the start

        // Add foreign key constraints (SQLite doesn't support adding FKs to existing tables via ALTER TABLE,
        // so we'll need to recreate the table if foreign keys are needed)
        // For now, we'll rely on application-level constraints

        // Add indexes if they don't exist
        $this->addProjectIndexes();
    }

    private function addProjectIndexes(): void
    {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_tasks_project_id ON tasks (project_id)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_parent_id ON tasks (parent_id)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_user_project_order ON tasks (user_id, project_id, order_index)",
        ];

        foreach ($indexes as $sql) {
            $this->database->query($sql);
        }

        $this->logger->info('Added project-related indexes', ['count' => count($indexes)]);
    }

    private function createIndexes(): void
    {
        // Always create these indexes
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_users_email ON users (email)",
            "CREATE INDEX IF NOT EXISTS idx_users_auth_token ON users (auth_token)",
            "CREATE INDEX IF NOT EXISTS idx_users_email_confirmation_token ON users (email_confirmation_token)",
            "CREATE INDEX IF NOT EXISTS idx_users_password_reset_token ON users (password_reset_token)",
            "CREATE INDEX IF NOT EXISTS idx_projects_user_id ON projects (user_id)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_user_id ON tasks (user_id)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_date ON tasks (date)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_user_date_order ON tasks (user_id, date, order_index)",
        ];

        // Check if tasks table has project_id and parent_id columns before creating indexes
        $tasksColumns = $this->getTableColumns('tasks');
        
        if (in_array('project_id', $tasksColumns)) {
            $indexes[] = "CREATE INDEX IF NOT EXISTS idx_tasks_project_id ON tasks (project_id)";
            // Create composite index if project_id exists
            $indexes[] = "CREATE INDEX IF NOT EXISTS idx_tasks_user_project_order ON tasks (user_id, project_id, order_index)";
        }
        
        if (in_array('parent_id', $tasksColumns)) {
            $indexes[] = "CREATE INDEX IF NOT EXISTS idx_tasks_parent_id ON tasks (parent_id)";
        }

        foreach ($indexes as $sql) {
            $this->database->query($sql);
        }

        $this->logger->info('Created database indexes', ['count' => count($indexes)]);
    }

    public function dropTables(): void
    {
        $this->logger->warning('Dropping all database tables');

        try {
            $this->database->beginTransaction();

            $this->database->query("DROP TABLE IF EXISTS tasks");
            $this->database->query("DROP TABLE IF EXISTS projects");
            $this->database->query("DROP TABLE IF EXISTS users");

            $this->database->commit();
            $this->logger->info('All tables dropped successfully');
        } catch (\Exception $e) {
            $this->database->rollback();
            $this->logger->error('Failed to drop tables', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function resetDatabase(): void
    {
        $this->logger->warning('Resetting database - dropping and recreating tables');
        $this->dropTables();
        $this->createTables();
    }

    /**
     * Check if a table exists in the database
     */
    private function tableExists(string $tableName): bool
    {
        $stmt = $this->database->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
            [$tableName]
        );
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false;
    }

    /**
     * Get column names for a table
     */
    private function getTableColumns(string $tableName): array
    {
        try {
            // Sanitize table name - only allow alphanumeric and underscore
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
                $this->logger->warning('Invalid table name for column check', ['table' => $tableName]);
                return [];
            }
            
            // PRAGMA doesn't support parameterized queries, but we've validated the input
            $stmt = $this->database->query("PRAGMA table_info({$tableName})");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_column($columns, 'name');
        } catch (\Exception $e) {
            // Table doesn't exist or error occurred
            $this->logger->debug('Could not get columns for table', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if date column allows NULL values
     * Returns true if column allows NULL, false if NOT NULL constraint exists
     */
    private function dateColumnAllowsNull(): bool
    {
        try {
            $stmt = $this->database->query("PRAGMA table_info(tasks)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($columns as $column) {
                if ($column['name'] === 'date') {
                    // In SQLite, 'notnull' is 0 if NULL is allowed, 1 if NOT NULL
                    return (int)$column['notnull'] === 0;
                }
            }
            
            return false; // Column not found, assume NOT NULL for safety
        } catch (\Exception $e) {
            $this->logger->error('Failed to check date column constraint', [
                'error' => $e->getMessage()
            ]);
            return false; // Assume NOT NULL for safety
        }
    }

    /**
     * Update tasks table to allow NULL dates
     * SQLite doesn't support ALTER COLUMN, so we need to recreate the table
     */
    public function updateTasksTableAllowNullDate(): void
    {
        $this->logger->info('Updating tasks table to allow NULL dates');

        try {
            // Check if date column already allows NULL
            if ($this->dateColumnAllowsNull()) {
                $this->logger->info('Date column already allows NULL, no update needed');
                return;
            }

            $this->database->beginTransaction();

            // Create temporary table with new schema (date allows NULL)
            $this->database->query("
                CREATE TABLE tasks_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    title TEXT NOT NULL,
                    description TEXT,
                    date TEXT,
                    order_index INTEGER NOT NULL,
                    completed INTEGER DEFAULT 0,
                    project_id INTEGER,
                    parent_id INTEGER,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                    FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                    FOREIGN KEY (parent_id) REFERENCES tasks (id) ON DELETE CASCADE
                )
            ");

            // Copy data from old table to new table
            // Check which columns exist in the old table
            $oldColumns = $this->getTableColumns('tasks');
            $hasProjectId = in_array('project_id', $oldColumns);
            $hasParentId = in_array('parent_id', $oldColumns);
            
            // Build column list for SELECT based on what exists
            $selectColumns = [
                'id',
                'user_id',
                'title',
                'description',
                'date',
                'order_index',
                'completed'
            ];
            
            if ($hasProjectId) {
                $selectColumns[] = 'project_id';
            } else {
                $selectColumns[] = 'NULL as project_id';
            }
            
            if ($hasParentId) {
                $selectColumns[] = 'parent_id';
            } else {
                $selectColumns[] = 'NULL as parent_id';
            }
            
            // Handle timestamps - use COALESCE to provide defaults if NULL
            $selectColumns[] = "COALESCE(created_at, datetime('now')) as created_at";
            $selectColumns[] = "COALESCE(updated_at, datetime('now')) as updated_at";
            
            $selectSql = "SELECT " . implode(', ', $selectColumns) . " FROM tasks";
            
            $this->database->query("
                INSERT INTO tasks_new (
                    id, user_id, title, description, date, order_index, completed, 
                    project_id, parent_id, created_at, updated_at
                )
                {$selectSql}
            ");

            // Drop old table
            $this->database->query("DROP TABLE tasks");

            // Rename new table
            $this->database->query("ALTER TABLE tasks_new RENAME TO tasks");

            // Recreate indexes (only task-related ones since other tables weren't affected)
            $this->addProjectIndexes();
            
            // Recreate other task indexes
            $taskIndexes = [
                "CREATE INDEX IF NOT EXISTS idx_tasks_user_id ON tasks (user_id)",
                "CREATE INDEX IF NOT EXISTS idx_tasks_date ON tasks (date)",
                "CREATE INDEX IF NOT EXISTS idx_tasks_user_date_order ON tasks (user_id, date, order_index)",
            ];
            
            foreach ($taskIndexes as $sql) {
                $this->database->query($sql);
            }

            $this->database->commit();
            $this->logger->info('Tasks table updated successfully to allow NULL dates');
        } catch (\Exception $e) {
            $this->database->rollback();
            $this->logger->error('Failed to update tasks table for NULL dates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
