<?php
// backend/setup.php

// This script is designed to be run from the command line (CLI) via SSH.
// It connects to the database and creates the necessary 'lottery_draws' table.

echo "[INFO] Starting database setup...\n";

// Use the centralized bootstrap file to handle environment loading and DB connection.
// This ensures consistency across the application.
require_once __DIR__ . '/bootstrap.php';

// The $db_connection variable is now available from bootstrap.php

try {
    // --- Table Creation Logic ---
    $table_name = 'lottery_draws';

    // SQL statement to create the table
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        draw_date DATE NOT NULL,
        number1 TINYINT NOT NULL,
        number2 TINYINT NOT NULL,
        number3 TINYINT NOT NULL,
        number4 TINYINT NOT NULL,
        number5 TINYINT NOT NULL,
        number6 TINYINT NOT NULL,
        special_number TINYINT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Execute the query
    if ($db_connection->query($sql) === TRUE) {
        echo "[SUCCESS] Table '{$table_name}' created successfully or already exists.\n";
    } else {
        // Provide a more detailed error message
        throw new Exception("Error creating table '{$table_name}': " . $db_connection->error);
    }

} catch (Exception $e) {
    // Catch any exception and print a fatal error
    echo "[FATAL] An error occurred during setup:\n";
    echo $e->getMessage() . "\n";
    // Exit with a non-zero status code to indicate failure
    exit(1);

} finally {
    // Always close the connection
    if ($db_connection) {
        $db_connection->close();
    }
}

// Exit with a zero status code to indicate success
exit(0);
