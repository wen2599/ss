<?php

// A minimal script to check if the .env loading is working.

echo "Attempting to load config...\n";
require_once __DIR__ . '/config.php';
echo "Config loaded.\n\n";

echo "Checking for TELEGRAM_BOT_TOKEN...\n";
$token = getenv('TELEGRAM_BOT_TOKEN');

if ($token) {
    echo "✅ Success: TELEGRAM_BOT_TOKEN is set.\n";
    // Show only a small, non-sensitive part of the token for verification
    echo "Token preview: " . substr($token, 0, 8) . "...\n";
} else {
    echo "❌ Failure: TELEGRAM_BOT_TOKEN is NOT set.\n";
    echo "Please check your .env file and file permissions.\n";
}

echo "\n--- All Environment Variables ---\n";
print_r($_ENV);
echo "\n--- Server Variables ---\n";
print_r($_SERVER);
echo "\n---------------------------------\n";

?>