<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db_connection.php';

function setupDatabase() {
    $conn = getDbConnection();
    if (!$conn) {
        echo "Database connection failed. Please check your .env file and the debug_db.php script for more information.\n";
        return;
    }

    echo "Database Setup\n";
    $sql = file_get_contents(__DIR__ . '/migration.sql');
    $statements = explode(';', $sql);

    $all_successful = true;
    foreach ($statements as $statement) {
        if (trim($statement) != '') {
            echo "----------------------------------------\n";
            echo "Executing:\n" . trim($statement) . ";\n";
            if ($conn->query($statement)) {
                echo "Successfully executed.\n";
            } else {
                echo "Error executing statement:\n";
                echo $conn->error . "\n";
                $all_successful = false;
            }
        }
    }

    echo "----------------------------------------\n";
    if ($all_successful) {
        echo "Database setup completed successfully!\n";
    } else {
        echo "Database setup completed with errors.\n";
    }

    $conn->close();
}

// Allow running from command line only
if (php_sapi_name() === 'cli') {
    setupDatabase();
} else {
    http_response_code(403);
    echo "This script can only be run from the command line.\n";
}
