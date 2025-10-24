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


// --- 2. Database Connection ---
try {
    // Connect to the MySQL server (without specifying a database to check if we can create it)
    $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbPort = $_ENV['DB_PORT'] ?? '3306';
    $dbUser = $_ENV['DB_USER'] ?? null;
    $dbPass = $_ENV['DB_PASSWORD'] ?? null;
    $dbName = $_ENV['DB_DATABASE'] ?? null;

    if (!$dbHost || !$dbUser || !$dbName) { // DB_PASSWORD can be empty
        echo "[ERROR] Database credentials (DB_HOST, DB_USER, DB_DATABASE) are not set in the .env file.\n";
        exit(1);
    }

    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[OK] Successfully connected to the MySQL server.\n";

    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS \`{$dbName}\`");
    echo "[OK] Ensured that database '{$dbName}' exists.\n";

    // Now, get the connection *through the application's function*, which will connect to the specific database
    $pdo = getDbConnection();
    echo "[OK] Successfully connected to the '{$dbName}' database.\n";

} catch (PDOException $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// --- 3. SQL Schema Import ---
function runMigrations(PDO $pdo): void {
    $sqlPath = __DIR__ . '/api/database/migration.sql';
    if (!file_exists($sqlPath)) {
        echo "[ERROR] SQL schema file not found at: " . $sqlPath . "\n";
        exit(1);
    }

    echo "Attempting to import schema from {$sqlPath}...\n";
    try {
        $sqlContent = file_get_contents($sqlPath);
        if ($sqlContent === false) {
            throw new Exception("Cannot read the migration file.");
        }

        $pdo->exec($sqlContent);

        echo "[OK] Successfully executed SQL statements from migration file.\n";

    } catch (Exception $e) {
        echo "[ERROR] An error occurred during SQL import: " . $e->getMessage() . "\n";
        exit(1);
    }
}

runMigrations($pdo);

echo "-----------------------\n";
echo "Setup completed successfully!\n";
