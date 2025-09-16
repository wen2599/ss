<?php
// backend/migrate.php

/**
 * A simple, dependency-free PHP database migration script.
 */

echo "Migration script starting...\n";

// Set a default timezone to avoid potential warnings
date_default_timezone_set('UTC');

// --- Database Connection ---
// We need the database connection logic from our API.
require_once __DIR__ . '/api/database.php';

try {
    $pdo = getDbConnection();
    echo "Database connection successful.\n";
} catch (Exception $e) {
    echo "Error: Database connection failed. Please check your .env file and database server.\n";
    echo "Details: " . $e->getMessage() . "\n";
    exit(1);
}

// --- 1. Ensure the migrations table exists ---
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    echo "Migrations table is ready.\n";
} catch (PDOException $e) {
    echo "Error: Could not create or verify the migrations table.\n";
    echo "Details: " . $e->getMessage() . "\n";
    exit(1);
}

// --- 2. Get all already executed migrations ---
$executedMigrations = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
echo count($executedMigrations) . " migrations have already been executed.\n";

// --- 3. Scan the migrations directory ---
$migrationsDir = __DIR__ . '/migrations';
$allMigrationFiles = glob($migrationsDir . '/*.php');
sort($allMigrationFiles); // Ensure they are in chronological order

echo "Found " . count($allMigrationFiles) . " migration files in the directory.\n";

// --- 4. Determine and run new migrations ---
$newMigrationsRun = 0;
foreach ($allMigrationFiles as $file) {
    $migrationName = basename($file);

    if (!in_array($migrationName, $executedMigrations)) {
        echo "----------------------------------------\n";
        echo "Executing new migration: $migrationName\n";

        try {
            // Each migration file is expected to return a PDO statement to be executed.
            $stmt = require $file;
            if ($stmt instanceof PDOStatement) {
                $stmt->execute();
            } else {
                // Handle cases where the file might not return a statement,
                // for example, if it uses `$pdo->exec()` directly.
                // We assume the file performs its own execution if it doesn't return a statement.
                echo "Migration file did not return a PDOStatement, assuming it handled its own execution.\n";
            }

            // Record the migration in the database
            $insertStmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (:migration)");
            $insertStmt->execute([':migration' => $migrationName]);

            echo "Successfully executed and recorded migration: $migrationName\n";
            $newMigrationsRun++;

        } catch (Exception $e) {
            echo "\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
            echo "CRITICAL ERROR during migration: $migrationName\n";
            echo "Details: " . $e->getMessage() . "\n";
            echo "Migration process halted. Please fix the error and try again.\n";
            echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
            exit(1);
        }
    }
}

echo "----------------------------------------\n";
if ($newMigrationsRun > 0) {
    echo "Migration script finished. Executed $newMigrationsRun new migrations.\n";
} else {
    echo "Migration script finished. Your database is already up to date.\n";
}

exit(0);
?>
