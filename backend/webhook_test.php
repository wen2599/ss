<?php
// backend/webhook_test.php

// Activate maximum error reporting to catch everything.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Executing webhook_test.php...<br>";

// --- Test 1: Can we load the configuration? ---
try {
    require_once 'config.php';
    echo "SUCCESS: config.php was loaded without fatal errors.<br>";
} catch (Exception $e) {
    echo "ERROR: A fatal error occurred while loading config.php: " . $e->getMessage() . "<br>";
    exit(); // Stop if config fails
}

// --- Test 2: Can we write to the directory? ---
$log_file_test = __DIR__ . '/permission_test.log';
$log_content = "File write test successful at " . date('Y-m-d H:i:s') . "\n";

if (file_put_contents($log_file_test, $log_content, FILE_APPEND)) {
    echo "SUCCESS: A log file was successfully written to " . htmlspecialchars($log_file_test) . "<br>";
} else {
    echo "ERROR: Failed to write to the log file. This confirms a file permission issue.<br>";
}

echo "<br>Test completed.";

?>
