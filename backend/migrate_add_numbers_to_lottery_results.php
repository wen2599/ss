<?php
// backend/migrate_add_numbers_to_lottery_results.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/env_loader.php';
$env_vars = load_env();

$db_host = $env_vars['DB_HOST'] ?? null;
$db_user = $env_vars['DB_USER'] ?? null;
$db_password = $env_vars['DB_PASSWORD'] ?? null;
$db_name = $env_vars['DB_NAME'] ?? null;

if (!$db_host || !$db_user || !$db_password || !$db_name) {
    die("Error: Database credentials are not fully set in your .env file or could not be loaded.\n");
}

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Database connected successfully.\n";

// --- Migration to add 'numbers' column to 'lottery_results' table ---
$table_name = 'lottery_results';
$column_name = 'numbers';

$result = $conn->query("SHOW COLUMNS FROM `{$table_name}` LIKE '{$column_name}'");

if ($result === false) {
    die("Error checking column existence: " . $conn->error . "\n");
}

if ($result->num_rows == 0) {
    echo "Attempting to add '{$column_name}' column to '{$table_name}' table...\n";
    // Using TEXT for more flexibility with numbers storage
    $alter_sql = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` VARCHAR(255) NOT NULL AFTER `draw_date`";
    if ($conn->query($alter_sql) === TRUE) {
        echo "Successfully added '{$column_name}' column to '{$table_name}' table.\n";
    } else {
        echo "Error adding '{$column_name}' column: " . $conn->error . "\n";
    }
} else {
    echo "'{$column_name}' column already exists in '{$table_name}' table. No migration needed.\n";
}

$conn->close();
echo "Migration script finished.\n";
