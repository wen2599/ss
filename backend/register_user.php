<?php

// --- EXTREME TEMPORARY DEBUGGING BLOCK START ---
// This block is to check if PHP script can execute AT ALL and write files.

$testLogFile = __DIR__ . '/super_early_test.log';
$testMessage = date('[Y-m-d H:i:s]') . ' [SUPER EARLY TEST] PHP script executed. Directory writable check.' . PHP_EOL;

// Attempt to write to a log file directly
if (file_put_contents($testLogFile, $testMessage, FILE_APPEND | LOCK_EX) !== false) {
    $outputMessage = "SUCCESS: PHP script executed and wrote to " . htmlspecialchars(basename($testLogFile)) . ". Check that file for details. Directory is writable.";
} else {
    $outputMessage = "FAILURE: PHP script executed but FAILED to write to " . htmlspecialchars(basename($testLogFile)) . ". Directory might NOT be writable or file_put_contents is disabled.";
}

// Also check if .env exists
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $outputMessage .= "<br>SUCCESS: .env file found at " . htmlspecialchars($envPath) . ".";
} else {
    $outputMessage .= "<br>FAILURE: .env file NOT found at " . htmlspecialchars($envPath) . ".";
}

echo $outputMessage;
exit; // Force script to exit here
// --- EXTREME TEMPORARY DEBUGGING BLOCK END ---

// The rest of the original register_user.php code is commented out or removed for this test.

?>