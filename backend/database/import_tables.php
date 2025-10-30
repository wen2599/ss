<?php
// File: backend/database/import_tables.php
// Description: A script to be run from the command line to import database tables.
// Usage (from your project's root directory via SSH): php backend/database/import_tables.php

// --- Environment Check ---
if (php_sapi_name() !== 'cli') {
    header("HTTP/1.1 403 Forbidden");
    die("ERROR: This script is designed to be run from the Command Line Interface (CLI) only.\n");
}

// --- Bootstrap ---
// Include the database configuration. The path is relative to this file.
require_once __DIR__ . '/../config/database.php';

echo "Attempting to import database tables...\n";

try {
    // --- Database Connection ---
    $conn = get_db_connection();
    echo "[SUCCESS] Connected to the database successfully.\n";

    // --- Read SQL Migration File ---
    $migration_file = __DIR__ . '/migrations.sql';
    if (!file_exists($migration_file)) {
        throw new Exception("Migration file not found at '{$migration_file}'");
    }

    $sql_commands = file_get_contents($migration_file);
    if ($sql_commands === false) {
        throw new Exception("Could not read the contents of the migration file.");
    }
    echo "[INFO] Read SQL commands from migrations.sql.\n";
    
    // --- Execute SQL Query ---
    // Use multi_query to execute all statements in the SQL file at once.
    if ($conn->multi_query($sql_commands)) {
        // We must loop through and clear the results from each query
        // to ensure the connection is ready for the next operation.
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
        echo "\n----------------------------------------------------\n";
        echo "[SUCCESS] All database tables were imported successfully!\n";
        echo "----------------------------------------------------\n";
    } else {
        throw new Exception("Failed to execute SQL commands: " . $conn->error);
    }

    // --- Close Connection ---
    $conn->close();

} catch (Exception $e) {
    echo "\n----------------------------------------------------\n";
    echo "[ERROR] An error occurred during the import process.\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "----------------------------------------------------\n";
    // Exit with a non-zero status code to indicate that an error occurred.
    exit(1);
}

// Exit with status 0 for success.
exit(0);

?>
