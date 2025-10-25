#!/usr/bin/env php
<?php

// This script initializes the database, creating the schema and inserting seed data.
// It relies on the shared bootstrap file for environment loading and DB connection.

echo "Database Setup Script\n";
echo "-----------------------\n";

// --- 1. Bootstrap ---
// Load environment variables and the database connection function.
// Note: We're requiring it from a directory as if this script were in the `backend/` directory.
require_once __DIR__ . '/api/bootstrap.php';

echo "[OK] Bootstrapped the application.\n";


// --- 2. Database Connection and Creation ---
try {
    // Environment variables are loaded by bootstrap.php
    $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbPort = (int)($_ENV['DB_PORT'] ?? '3306'); // Explicit type conversion for port
    $dbUser = $_ENV['DB_USER'] ?? null;
    $dbPass = $_ENV['DB_PASSWORD'] ?? null;
    $dbName = $_ENV['DB_DATABASE'] ?? null;

    // --- Diagnostic Output ---
    echo "\n--- [DIAGNOSTIC INFO] ---\n";
    echo "Attempting to use the following configuration:\n";
    echo "DB_HOST: " . ($dbHost ? $dbHost : "Not Set") . "\n";
    echo "DB_PORT: " . ($dbPort ? $dbPort : "Not Set") . "\n";
    echo "DB_DATABASE: " . ($dbName ? $dbName : "Not Set") . "\n";
    echo "DB_USER: " . ($dbUser ? $dbUser : "Not Set") . "\n";
    echo "DB_PASSWORD: " . ($dbPass ? "[Set]" : "Not Set") . "\n";
    echo "---------------------------\n\n";
    // --- End Diagnostic ---

    if (!$dbHost || !$dbUser || !$dbName) { 
        echo "[ERROR] Database credentials (DB_HOST, DB_USER, DB_DATABASE) are not set in the .env file.\n"; 
        exit(1);
    }

    // Connect to the MySQL server (without specifying a database to check if we can create it)
    // Use the port variable that has been explicitly converted to an integer
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[OK] Successfully connected to the MySQL server.\n";

    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
    echo "[OK] Ensured that database '{$dbName}' exists.\n";

    // Now, get the connection *through the application's function*, which will connect to the specific database
    $pdo = getDbConnection();
    echo "[OK] Successfully connected to the '{$dbName}' database.\n";

} catch (PDOException $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
    // Log full error details only if debug mode is enabled
    if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
        error_log("Database Setup Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }
    exit(1);
} catch (Exception $e) {
    echo "[ERROR] An unexpected error occurred during database setup: " . $e->getMessage() . "\n";
    if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
        error_log("Database Setup Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }
    exit(1);
}

// --- 3. SQL Schema Import ---
/**
 * Executes SQL migration statements from a file.
 * @param PDO $pdo The PDO database connection object.
 * @return void
 * @throws Exception If the migration file is not found or cannot be read, or if SQL execution fails.
 */
function runMigrations(PDO $pdo): void {
    $sqlPath = __DIR__ . '/api/database/migration.sql';
    if (!file_exists($sqlPath)) {
        throw new Exception("SQL schema file not found at: " . $sqlPath);
    }

    echo "Attempting to import schema from {$sqlPath}...\n";
    try {
        $sqlContent = file_get_contents($sqlPath);
        if ($sqlContent === false) {
            throw new Exception("Cannot read the migration file.");
        }

        // Execute all SQL statements. For production, consider splitting into individual statements
        // and handling transactions for more robust migration.
        $pdo->exec($sqlContent);

        echo "[OK] Successfully executed SQL statements from migration file.\n";

    } catch (Exception $e) {
        // Catch general exceptions for file operations and re-throw if needed, or handle specifically.
        throw new Exception("An error occurred during SQL import: " . $e->getMessage(), 0, $e);
    }
}

runMigrations($pdo);

echo "-----------------------\n";
echo "Setup completed successfully!\n";
