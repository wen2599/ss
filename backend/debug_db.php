<?php
// backend/debug_db.php

// --- Force Error Display ---
// This is critical for debugging when log files are not accessible.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "Starting database connection debug...\n";

try {
    // 1. Load Environment Variables
    echo "Loading environment variables...\n";
    require_once __DIR__ . '/env_loader.php';
    echo "env_loader.php loaded successfully.\n";

    // Check if critical DB variables are loaded by getenv()
    $db_host = getenv('DB_HOST');
    $db_user = getenv('DB_USER');
    $db_name = getenv('DB_NAME');
    $db_pass_exists = getenv('DB_PASSWORD') !== false;

    if (!$db_host || !$db_user || !$db_name) {
        echo "\nERROR: One or more environment variables (DB_HOST, DB_USER, DB_NAME) are not loaded.\n";
        echo "DB_HOST: " . ($db_host ? $db_host : 'NOT SET') . "\n";
        echo "DB_USER: " . ($db_user ? $db_user : 'NOT SET') . "\n";
        echo "DB_NAME: " . ($db_name ? $db_name : 'NOT SET') . "\n";
        echo "DB_PASSWORD is set: " . ($db_pass_exists ? 'Yes' : 'No') . "\n";
        exit;
    } else {
        echo "All required environment variables appear to be loaded.\n";
    }

    // 2. Load Database Connection Logic
    echo "Loading database connection logic...\n";
    require_once __DIR__ . '/db_connection.php';
    echo "db_connection.php loaded successfully.\n";

    // 3. Attempt to Connect
    echo "Attempting to establish database connection...\n";
    $conn = get_db_connection();

    // 4. Report Success
    if ($conn) {
        echo "\nSUCCESS: Database connection established successfully!\n";
        echo "Server version: " . $conn->server_info . "\n";
        $conn->close();
    } else {
        // This case should ideally not be reached if exceptions are thrown correctly.
        echo "\nFAILURE: get_db_connection() returned null without throwing an exception.\n";
    }

} catch (Exception $e) {
    // 5. Report Failure
    echo "\n--- DATABASE CONNECTION FAILED ---\n";
    echo "An exception was caught:\n";
    echo "Error Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";

?>
