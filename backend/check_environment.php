<?php
header('Content-Type: text/plain; charset=utf-8');

echo "--- Backend Environment Diagnostic Script ---\n\n";

$allChecksPassed = true;
$logFilePath = __DIR__ . '/../backend.log';
$envFilePath = __DIR__ . '/.env';

// --- Check 1: PHP Version ---
echo "1. Checking PHP Version...";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "OK (v" . PHP_VERSION . ")\n";
} else {
    echo "FAIL (v" . PHP_VERSION . " - Recommended: 7.4.0 or higher)\n";
    $allChecksPassed = false;
}

// --- Check 2: .env File ---
echo "2. Checking .env File...";
if (file_exists($envFilePath)) {
    if (is_readable($envFilePath)) {
        echo "OK (Exists and is readable)\n";
    } else {
        echo "FAIL (.env file exists but is NOT READABLE)\n";
        $allChecksPassed = false;
    }
} else {
    echo "FAIL (.env file does NOT EXIST at {$envFilePath})\n";
    $allChecksPassed = false;
}

// --- Check 3: Log File Permissions ---
echo "3. Checking Log File Permissions...";
if (file_exists($logFilePath)) {
    if (is_writable($logFilePath)) {
        echo "OK (Log file is writable)\n";
    } else {
        echo "FAIL (Log file exists but is NOT WRITABLE)\n";
        $allChecksPassed = false;
    }
} else {
    if (is_writable(dirname($logFilePath))) {
        echo "OK (Log directory is writable, file will be created)\n";
    } else {
        echo "FAIL (Log file does not exist and its directory is NOT WRITABLE)\n";
        $allChecksPassed = false;
    }
}

// --- Check 4: Required PHP Extensions ---
echo "4. Checking for 'pdo_mysql' extension...";
if (extension_loaded('pdo_mysql')) {
    echo "OK (Installed)\n";
} else {
    echo "FAIL (NOT INSTALLED - This is critical for database connectivity)\n";
    $allChecksPassed = false;
}

// --- Check 5: Database Connection ---
echo "5. Attempting Database Connection...\n";
if ($allChecksPassed) {
    // Only attempt connection if basic checks are fine
    require_once __DIR__ . '/bootstrap.php';
    try {
        $pdo = get_db_connection();
        echo "   ... OK (Successfully connected to the database)\n";
    } catch (PDOException $e) {
        echo "   ... FAIL (Connection failed: " . $e->getMessage() . ")\n";
        $allChecksPassed = false;
    }
} else {
    echo "   ... SKIPPED (Due to previous failures)\n";
}

// --- Final Summary ---
echo "\n--- DIAGNOSTIC COMPLETE ---\n";
if ($allChecksPassed) {
    echo "RESULT: All critical environment checks passed successfully.\n";
} else {
    echo "RESULT: One or more critical environment checks failed. Please review the output above to resolve the issues.\n";
}
echo "---------------------------\n";

?>
