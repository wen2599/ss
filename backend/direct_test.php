<?php

// --- Standalone Command-Line Diagnostic Tool ---
// This script is designed to be run directly from the SSH terminal via `php backend/direct_test.php`
// It bypasses the web server (Apache/Nginx) to test the core PHP application components.

echo "--- Starting Direct Application Test ---\n\n";

// --- Step 1: Test Core File Includes ---
echo "Step 1: Loading core helper files...\n";
try {
    require_once __DIR__ . '/config.php';
    echo "  [SUCCESS] All files in config.php were included without fatal errors.\n\n";
} catch (Throwable $t) {
    echo "  [FATAL] A fatal error occurred while including files. This is the root cause.\n";
    echo "  Error: " . $t->getMessage() . "\n";
    echo "  File: " . $t->getFile() . " on line " . $t->getLine() . "\n\n";
    exit(1); // Exit immediately on fatal error.
}

// --- Step 2: Test Environment Variable Loading ---
echo "Step 2: Checking if .env variables are loaded...\n";
$telegramToken = getenv('TELEGRAM_BOT_TOKEN');
$adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
$dbHost = getenv('DB_HOST');

if ($telegramToken && $adminChatId && $dbHost) {
    echo "  [SUCCESS] Environment variables (tokens, db host) seem to be loaded.\n\n";
} else {
    echo "  [FAILURE] Critical environment variables are NOT loaded.\n";
    echo "  - TELEGRAM_BOT_TOKEN loaded: " . ($telegramToken ? 'Yes' : 'No') . "\n";
    echo "  - TELEGRAM_ADMIN_CHAT_ID loaded: " . ($adminChatId ? 'Yes' : 'No') . "\n";
    echo "  - DB_HOST loaded: " . ($dbHost ? 'Yes' : 'No') . "\n";
    echo "  This strongly suggests the .env file at " . realpath(__DIR__ . '/.env') . " is not being read correctly.\n\n";
    exit(1);
}

// --- Step 3: Test Database Connection ---
echo "Step 3: Attempting to connect to the database...\n";
$pdo = get_db_connection();
if ($pdo) {
    echo "  [SUCCESS] Database connection was successful.\n\n";
} else {
    echo "  [FAILURE] Could not connect to the database.\n";
    echo "  Please double-check the DB_* credentials in your .env file.\n\n";
    exit(1);
}

// --- Step 4: Test Telegram API ---
echo "Step 4: Attempting to send a test message via Telegram API...\n";
$testMessage = "✅ Hello from the direct_test.php script! If you received this, your Telegram Bot Token and Admin Chat ID are correct.";

if (sendTelegramMessage($adminChatId, $testMessage)) {
    echo "  [SUCCESS] Test message sent to your Telegram admin account.\n";
    echo "  If you received the message, the bot is fully functional.\n\n";
} else {
    echo "  [FAILURE] Failed to send Telegram message.\n";
    echo "  This likely means your TELEGRAM_BOT_TOKEN is invalid or the bot is blocked.\n";
    echo "  Please verify the token and ensure your bot can message the admin chat ID.\n\n";
    exit(1);
}

echo "--- Direct Application Test Completed Successfully! ---\n";
echo "If all steps above passed, the core application is working correctly. The issue likely lies with the web server configuration (.htaccess or Nginx config).\n";

?>