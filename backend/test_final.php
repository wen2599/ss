<?php
// backend/test_final.php

// This script has NO external dependencies. It tests the core PHP functionality and file permissions.
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "--- Final Test Initialized ---<br>";

$test_log_file = __DIR__ . '/final_test_write.log';
$content = "PHP execution and file write successful at " . date('Y-m-d H:i:s') . "\n";

if (is_writable(__DIR__)) {
    echo "SUCCESS: The directory " . __DIR__ . " is writable.<br>";
    if (file_put_contents($test_log_file, $content)) {
        echo "SUCCESS: Log file created at " . htmlspecialchars($test_log_file) . "<br>";
    } else {
        echo "FAILURE: The directory is writable, but file_put_contents() failed for an unknown reason.<br>";
    }
} else {
    echo "FAILURE: The directory " . __DIR__ . " is NOT writable. This is a file permission issue.<br>";
}

echo "--- Test Complete ---";

?>
