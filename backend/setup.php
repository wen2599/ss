#!/usr/bin/env php
<?php

// A simple script to set up the database from the command line.

echo "Database Setup Script\n";
echo "-----------------------\n";

// --- 1. Environment Variable Loading ---
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    echo "[ERROR] .env file not found at: " . $envPath . "\n";
    exit(1);
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) {
        continue;
    }
    list($name, $value) = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value, '"'); // Trim quotes from value

    if (!empty($name)) {
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
echo "[OK] Loaded environment variables from .env file.\n";

// --- 2. Database Connection ---
$dbHost = $_ENV['DB_HOST'] ?? null;
$dbPort = $_ENV['DB_PORT'] ?? '3306';
$dbUser = $_ENV['DB_USER'] ?? null;
$dbPass = $_ENV['DB_PASSWORD'] ?? null;
$dbName = $_ENV['DB_DATABASE'] ?? null;

if (!$dbHost || !$dbUser || !$dbPass || !$dbName) {
    echo "[ERROR] Database credentials (DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE) are not set in the .env file.\n";
    exit(1);
}

try {
    // Connect to the MySQL server first (without specifying a database)
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[OK] Successfully connected to the MySQL server.\n";

    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS \`{$dbName}\`");
    echo "[OK] Ensured that database '{$dbName}' exists.\n";

    // Re-connect, this time selecting the database
    $pdo->exec("USE \`{$dbName}\`");
    echo "[OK] Selected database '{$dbName}'.\n";

} catch (PDOException $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// --- 3. SQL Schema Import ---
$sqlPath = __DIR__ . '/api/database/migration.sql';
if (!file_exists($sqlPath)) {
    echo "[ERROR] SQL schema file not found at: " . $sqlPath . "\n";
    exit(1);
}

echo "Attempting to import schema from {$sqlPath}...\n";
try {
    $sqlContent = file_get_contents($sqlPath);

    // Remove comments and split into individual statements
    $sqlContent = preg_replace('/--.*/', '', $sqlContent);
    $statements = array_filter(array_map('trim', explode(';', $sqlContent)));

    $totalStatements = count($statements);
    $executedStatements = 0;

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            $executedStatements++;
        }
    }

    echo "[OK] Successfully executed {$executedStatements} of {$totalStatements} SQL statements.\n";

} catch (PDOException $e) {
    echo "[ERROR] An error occurred during SQL import: " . $e->getMessage() . "\n";
    echo "       The failing statement might be near: '{$statement}'\n";
    exit(1);
}

echo "-----------------------\n";
echo "Setup completed successfully!\n";
