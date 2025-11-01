<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db_connection.php';

function setupDatabase() {
    $conn = getDbConnection();
    $sql = file_get_contents(__DIR__ . '/migration.sql');
    $statements = explode(';', $sql);

    foreach ($statements as $statement) {
        if (trim($statement) != '') {
            if (!$conn->query($statement)) {
                echo "Error executing statement: " . $statement . "\n";
                echo "Error: " . $conn->error . "\n";
                // Decide if you want to stop on error
            }
        }
    }

    echo "Database setup complete.\n";
    $conn->close();
}

// If this script is run from the command line, execute the setup function.
if (php_sapi_name() === 'cli') {
    setupDatabase();
}
