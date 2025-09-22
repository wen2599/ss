<?php
// This script is intended to be run from the command line (CLI).
// Usage: php backend/setup_database.php

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

echo "Database Setup Script\n";
echo "=======================\n\n";

// 1. Load Configuration
// Use absolute path to ensure it works regardless of where the script is called from.
require_once __DIR__ . '/config.php';

// 2. Load SQL Schema
$schema_path = __DIR__ . '/data_table_schema.sql';
if (!file_exists($schema_path)) {
    die("ERROR: SQL schema file not found at {$schema_path}\n");
}
$sql_schema = file_get_contents($schema_path);
if (empty($sql_schema)) {
    die("ERROR: SQL schema file is empty.\n");
}
echo "✓ SQL schema file loaded successfully.\n";

// 3. Connect to the Database
try {
    // Connect to MySQL server without specifying a database name first
    $pdo = new PDO("mysql:host=$db_host;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "✓ Database '$db_name' created or already exists.\n";

    // Re-connect to the specific database
    $pdo->exec("USE `$db_name`;");
    echo "✓ Connected to database '$db_name' successfully.\n";

} catch (PDOException $e) {
    die("ERROR: Could not connect to the database server. " . $e->getMessage() . "\n");
}

// 4. Execute the Schema
try {
    $pdo->exec($sql_schema);
    echo "✓ Tables created successfully based on schema file.\n\n";
    echo "SUCCESS: Database setup is complete.\n";
} catch (PDOException $e) {
    die("ERROR: Failed to execute the SQL schema. " . $e->getMessage() . "\n");
}

?>
