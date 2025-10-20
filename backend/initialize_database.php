<?php
// backend/initialize_database.php
// A script to initialize the database tables from the schema file.
// Can be run from the command line: php initialize_database.php

// Allow running only from the command line interface (CLI)
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/bootstrap.php'; // bootstrap.php already loads .env and establishes PDO

try {
    // Read the SQL schema file
    $sql = file_get_contents(__DIR__ . '/database_schema.sql');

    if ($sql === false) {
        die("Error: Could not read database_schema.sql file.\n");
    }

    // Execute the SQL queries
    $pdo->exec($sql);

    echo "Database tables created/updated successfully from database_schema.sql!\n";

} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage() . "\n");
}
