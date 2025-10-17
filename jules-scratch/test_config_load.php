<?php
// This script is designed to be run from the repository root or a subdirectory.
// It tests whether including the main configuration file causes a fatal error.

echo "--- Test Script Started ---\n\n";

// Set error reporting to catch everything
error_reporting(E_ALL);
ini_set('display_errors', 1);

// The script is in `jules-scratch/`, the config is in `backend/`.
// `__DIR__` gives the directory of the current script.
$configFile = __DIR__ . '/../backend/config.php';

// Check if the file exists before trying to include it.
if (!file_exists($configFile)) {
    echo ">>> FAILURE: Config file not found at expected path.\n";
    echo "    Script directory (__DIR__): " . __DIR__ . "\n";
    echo "    Calculated config path: " . $configFile . "\n";
    // Attempt to get a real path for better debugging
    $realPath = realpath($configFile);
    if ($realPath) {
        echo "    Resolved real path: " . $realPath . "\n";
    } else {
        echo "    Could not resolve real path.\n";
    }
    exit("--- Test Script Finished with Error ---\n");
}

echo "Attempting to include '$configFile'...\n";

// The require_once will trigger a fatal error if there's a problem in any of the included files.
// My fix in db_operations.php is intended to prevent this.
require_once $configFile;

echo ">>> Successfully included 'config.php' without fatal errors.\n\n";

// Now, let's check if the get_db_connection function returns the expected error structure.
echo "Calling get_db_connection()...\n";

$db_connection_result = get_db_connection();

if (is_array($db_connection_result) && isset($db_connection_result['db_error'])) {
    echo ">>> SUCCESS: get_db_connection() returned the expected error array.\n";
    echo "    Error message: " . $db_connection_result['db_error'] . "\n";
    echo "\n    This indicates the fix is working correctly. The application no longer crashes.\n";
} elseif (is_object($db_connection_result) && get_class($db_connection_result) === 'PDO') {
    echo ">>> UNEXPECTED SUCCESS: A PDO connection was established.\n";
    echo "    This means the PDO extension IS available, and the original problem may have been different.\n";
    echo "    However, the bot should now be responsive.\n";
} else {
    echo ">>> FAILURE: get_db_connection() returned an unexpected value or a fatal error occurred before this point.\n";
    echo "    Return value was: ";
    print_r($db_connection_result);
    echo "\n";
}

echo "\n--- Test Script Finished ---\n";
?>