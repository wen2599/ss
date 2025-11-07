<?php
// Diagnostic script to test file write permissions.
error_reporting(E_ALL);
ini_set('display_errors', 1);

$test_log_file = __DIR__ . '/diag_log.txt';
$timestamp = date('Y-m-d H:i:s');
$log_message = "Diagnostic script executed at: {$timestamp}\n";

// Try to write to the log file
if (file_put_contents($test_log_file, $log_message, FILE_APPEND)) {
    echo "<h1>Success!</h1>";
    echo "<p>The diagnostic log file was written successfully at: <code>{$test_log_file}</code></p>";
    echo "<p>This means file permissions are likely correct.</p>";
    echo "<p>The problem is probably a fatal PHP error in the main `telegram.php` script.</p>";
} else {
    echo "<h1>Failure!</h1>";
    echo "<p>The diagnostic log file could NOT be written at: <code>{$test_log_file}</code></p>";
    echo "<p>This strongly indicates a <strong>server file permission issue</strong>.</p>";
    echo "<p>The web server user does not have permission to create files in this directory.</p>";
}
