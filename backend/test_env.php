<?php
// backend/test_env.php

// Enable error reporting to see the exact problem in the browser
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "--- ENV Loader Test ---<br>";
echo "Attempting to load config_loader.php...<br>";

try {
    // Include the config loader, which attempts to load the .env file
    require_once __DIR__ . '/utils/config_loader.php';
    echo "Successfully included config_loader.php.<br>";
} catch (Throwable $t) {
    echo "<strong>Fatal Error:</strong> Failed to include config_loader.php. The script crashed.<br>";
    echo "Error message: " . $t->getMessage() . "<br>";
    exit;
}

echo "Testing for DB_HOST environment variable...<br>";

// Attempt to get the DB_HOST variable
$db_host = getenv('DB_HOST');

if ($db_host) {
    echo "<strong>Success!</strong> DB_HOST is: '" . htmlspecialchars($db_host) . "'";
} else {
    $expected_path = realpath(__DIR__ . '/../.env');
    echo "<strong>Failure:</strong> DB_HOST environment variable is NOT set.<br>";
    echo "The script looked for the .env file at the following absolute path: " . ($expected_path ?: "Path does not exist: " . __DIR__ . '/../.env');
}

echo "<br>--- Test Complete ---";

?>
