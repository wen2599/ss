<?php
// backend/debug_env.php
// This script is for command-line debugging only.
// It is NOT safe to run this from a web browser.

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "--- Starting Environment Debugger ---\n";

// Include the environment loader
require_once __DIR__ . '/env_loader.php';

echo "\n--- Loaded Environment Variables ---\n";
print_r($_ENV);
echo "------------------------------------\n";

echo "\n--- Checking for Specific Variables ---\n";
$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
$backend_url = $_ENV['BACKEND_URL'] ?? null;

if ($bot_token) {
    echo "OK: TELEGRAM_BOT_TOKEN is loaded.\n";
} else {
    echo "ERROR: TELEGRAM_BOT_TOKEN is NOT loaded.\n";
}

if ($backend_url) {
    echo "OK: BACKEND_URL is loaded.\n";
} else {
    echo "ERROR: BACKEND_URL is NOT loaded.\n";
}

echo "-------------------------------------\n";
echo "--- Debugger Finished ---\n";
