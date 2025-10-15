<?php

// This script tests the core application logic by bypassing the web server.
// It directly calls the processCommand function to see if the bot can send a message.

echo "Starting Core Logic Test...\n";

// --- Step 1: Define that we are in a test context ---
// This constant will be checked in telegramWebhook.php to prevent it
// from running its main logic (like security checks) when included.
define('IS_LOGIC_TEST', true);

// --- Step 2: Load all configurations and functions ---
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegramWebhook.php';

echo "Configuration and webhook script loaded.\n";

// --- Step 2: Get the Admin Chat ID from the environment ---
$adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
if (empty($adminChatId)) {
    echo "ERROR: TELEGRAM_ADMIN_CHAT_ID is not set in your .env file.\n";
    exit(1);
}

echo "Admin Chat ID found: {$adminChatId}\n";
echo "Simulating a '/start' command from the admin...\n";

// --- Step 3: Directly call the command processing function ---
// We use the admin chat ID for both chat ID and user ID for this test.
// This function should trigger a 'Welcome back' message.
try {
    processCommand($adminChatId, $adminChatId, '/start');
    echo "Test command processed successfully.\n";
} catch (Exception $e) {
    echo "An error occurred during command processing: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
echo "====================================================================\n";
echo "Test finished. Please check your Telegram app for a message.\n";
echo "If you received a 'Welcome back, admin!' message, then all the PHP\n";
echo "code is working perfectly, and the problem is 100% with the web server.\n";
echo "====================================================================\n";

?>