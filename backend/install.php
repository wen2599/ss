<?php
// backend/install.php
// A simple script to initialize the database from setup.sql

// This script is intended to be run from the command line.
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/bootstrap.php';

try {
    $pdo = get_db_connection();
    $sql = file_get_contents(__DIR__ . '/setup.sql');

    if ($sql === false) {
        throw new Exception("Could not read setup.sql file.");
    }

    $pdo->exec($sql);
    echo "Database setup completed successfully.\n";

} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("An error occurred: " . $e->getMessage() . "\n");
}
