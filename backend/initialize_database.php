<?php

// This script should be run from the command line to set up the database tables.

require_once __DIR__ . '/bootstrap.php'; // Load common functionalities including get_db_connection

echo "--- Database Initialization Script ---\n\n";

// 1. Get Database Connection
echo "Step 1: Connecting to the database...\n";
$pdo = get_db_connection();
if (is_array($pdo) && isset($pdo['db_error'])) {
    echo "[FAILURE] " . $pdo['db_error'] . "\n";
    write_log("Database initialization failed: " . $pdo['db_error']);
    exit(1);
}
if (!$pdo) {
    echo "[FAILURE] Could not connect to the database. Please check your .env credentials.\n";
    write_log("Database initialization failed: Could not connect to the database.");
    exit(1);
}
echo "  [SUCCESS] Database connection established.\n\n";

// 2. Read the SQL Schema File
echo "Step 2: Reading the database schema file (database_schema.sql)...\n";
$sql_file = __DIR__ . '/database_schema.sql';
if (!file_exists($sql_file)) {
    echo "[FAILURE] `database_schema.sql` not found in the backend directory.\n";
    write_log("Database initialization failed: database_schema.sql not found.");
    exit(1);
}
$sql = file_get_contents($sql_file);
if (empty($sql)) {
    echo "[FAILURE] `database_schema.sql` is empty.\n";
    write_log("Database initialization failed: database_schema.sql is empty.");
    exit(1);
}
echo "  [SUCCESS] SQL schema file read successfully.\n\n";

// 3. Execute the SQL to Create Tables
echo "Step 3: Executing SQL to create tables...\n";
try {
    $pdo->exec($sql);
    echo "  [SUCCESS] All tables have been created successfully (or already existed).\n\n";
    write_log("Database initialization: All tables created successfully.");
} catch (PDOException $e) {
    echo "[FAILURE] An error occurred while creating the tables.\n";
    echo "  Error: " . $e->getMessage() . "\n";
    write_log("Database initialization failed during table creation: " . $e->getMessage());
    exit(1);
}

echo "--- Database Initialization Complete! ---\n";
write_log("Database initialization script completed successfully.");

?>