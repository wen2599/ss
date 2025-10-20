<?php

// This script should be run from the command line to set up the database tables.

require_once __DIR__ . '/bootstrap.php'; // Load common functionalities

echo "--- Database Initialization Script ---\n\n";

try {
    // 1. Get Database Connection
    echo "Step 1: Connecting to the database...\n";
    $pdo = get_db_connection();
    echo "  [SUCCESS] Database connection established.\n\n";

    // 2. Read the SQL Schema File
    echo "Step 2: Reading the database schema file (database_schema.sql)...\n";
    $sql_file = __DIR__ . '/database_schema.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("`database_schema.sql` not found in the backend directory.");
    }
    $sql = file_get_contents($sql_file);
    if (empty($sql)) {
        throw new Exception("`database_schema.sql` is empty.");
    }
    echo "  [SUCCESS] SQL schema file read successfully.\n\n";

    // 3. Execute the SQL to Create Tables
    echo "Step 3: Executing SQL to create tables...\n";
    $pdo->exec($sql);
    echo "  [SUCCESS] All tables have been created successfully (or already existed).\n\n";

    echo "--- Database Initialization Complete! ---\n";
    write_log("Database initialization script completed successfully.");

} catch (PDOException $e) {
    echo "[FAILURE] A database error occurred.\n";
    echo "  Error: " . $e->getMessage() . "\n";
    write_log("Database initialization failed: " . $e->getMessage());
    exit(1);
} catch (Exception $e) {
    echo "[FAILURE] An unexpected error occurred.\n";
    echo "  Error: " . $e->getMessage() . "\n";
    write_log("Database initialization failed: " . $e->getMessage());
    exit(1);
}

?>