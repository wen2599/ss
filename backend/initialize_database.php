<?php

// This script should be run from the command line to set up the database tables.

require_once __DIR__ . '/config.php';

echo "--- Database Initialization Script ---\n\n";

// 1. Get Database Connection
echo "Step 1: Connecting to the database...\n";
$pdo = get_db_connection();
if (!$pdo) {
    echo "[FAILURE] Could not connect to the database. Please check your .env credentials.\n";
    exit(1);
}
echo "  [SUCCESS] Database connection established.\n\n";

// 2. Read the SQL Schema File
echo "Step 2: Reading the database schema file (database_schema.sql)...\n";
$sql_file = __DIR__ . '/database_schema.sql';
if (!file_exists($sql_file)) {
    echo "[FAILURE] `database_schema.sql` not found in the backend directory.\n";
    exit(1);
}
$sql = file_get_contents($sql_file);
if (empty($sql)) {
    echo "[FAILURE] `database_schema.sql` is empty.\n";
    exit(1);
}
echo "  [SUCCESS] SQL schema file read successfully.\n\n";

// 3. Execute the SQL to Create Tables
echo "Step 3: Executing SQL to create tables...\n";
try {
    $pdo->exec($sql);
    echo "  [SUCCESS] All tables have been created successfully (or already existed).\n\n";
} catch (PDOException $e) {
    echo "[FAILURE] An error occurred while creating the tables.\n";
    echo "  Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "--- Database Initialization Complete! ---\n";

?>