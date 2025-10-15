<?php
header('Content-Type: text/plain; charset=utf-8');

echo "--- Environment Variable Diagnostic --- \n\n";

$envPath = __DIR__ . '/.env';

echo "Checking for .env file at: " . $envPath . "\n";

if (file_exists($envPath)) {
    echo "SUCCESS: .env file found.\n";
} else {
    echo "ERROR: .env file NOT FOUND at the expected location.\n";
    exit;
}

if (is_readable($envPath)) {
    echo "SUCCESS: .env file is readable.\n\n";
} else {
    echo "ERROR: .env file is NOT READABLE. Please check file permissions (e.g., `chmod 644 .env`).\n";
    exit;
}

// --- Test loading the .env file ---
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    echo "ERROR: Failed to read the .env file content, even though it exists and is readable.\n";
    exit;
}

echo "--- Simulating .env loading --- \n";
$found_db_host = false;
$found_telegram_secret = false;

foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        if ($name === 'DB_HOST') {
            $found_db_host = true;
            echo "Found DB_HOST variable.\n";
        }
        if ($name === 'TELEGRAM_WEBHOOK_SECRET') {
            $found_telegram_secret = true;
            echo "Found TELEGRAM_WEBHOOK_SECRET variable.\n";
        }
    }
}

echo "\n--- Verification --- \n";
if ($found_db_host) {
    echo "SUCCESS: DB_HOST was found in the .env file.\n";
} else {
    echo "ERROR: DB_HOST was NOT found in the .env file.\n";
}

if ($found_telegram_secret) {
    echo "SUCCESS: TELEGRAM_WEBHOOK_SECRET was found in the .env file.\n";
} else {
    echo "ERROR: TELEGRAM_WEBHOOK_SECRET was NOT found in the .env file.\n";
}

echo "\n--- Final Diagnosis --- \n";
if ($found_db_host && $found_telegram_secret) {
    echo "The .env file seems correct. If issues persist, the problem might be with the `putenv` function on your server's PHP configuration or another server-level issue.\n";
} else {
    echo "One or more critical variables are missing from your .env file. Please ensure the file is complete and correctly formatted.\n";
}

?>