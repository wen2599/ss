<?php

// --- Standalone Command-Line Diagnostic Tool ---
// This script is designed to be run directly from the SSH terminal via `php diag.php`
// It bypasses the web server (Apache/Nginx) to test the core PHP application components.

echo "--- Starting Full Diagnostic Test ---\n\n";

// --- Step 1: Test Core File Includes ---
echo "Step 1: Loading core configuration...\n";
try {
    // Manually load the environment for this standalone script
    function load_env_diag() {
        $envPath = __DIR__ . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, '"');
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    load_env_diag();

    // Include only what's necessary for the diagnostic
    require_once __DIR__ . '/telegram_helpers.php';
    echo "  [SUCCESS] Core components were included without a fatal error.\n\n";
} catch (Throwable $t) {
    echo "  [FATAL] A fatal error occurred during inclusion. This is the root cause.\n";
    echo "  Error: " . $t->getMessage() . "\n";
    echo "  File: " . $t->getFile() . " on line " . $t->getLine() . "\n\n";
    exit(1); // Exit immediately on fatal error.
}

// --- Step 2: Test Environment Variable Loading ---
echo "Step 2: Checking if .env variables are loaded...\n";
$telegramToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
$adminChatId = $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? null;

if ($telegramToken && $adminChatId) {
    echo "  [SUCCESS] Essential environment variables (TELEGRAM_BOT_TOKEN, TELEGRAM_ADMIN_CHAT_ID) are loaded.\n\n";
} else {
    echo "  [FAILURE] Critical environment variables are NOT loaded.\n";
    echo "  - TELEGRAM_BOT_TOKEN loaded: " . ($telegramToken ? 'Yes' : 'No') . "\n";
    echo "  - TELEGRAM_ADMIN_CHAT_ID loaded: " . ($adminChatId ? 'Yes' : 'No') . "\n";
    echo "  This strongly suggests the .env file at " . realpath(__DIR__ . '/.env') . " is not being read correctly.\n\n";
    exit(1);
}

// --- Step 3: Test Telegram API ---
echo "Step 3: Attempting to send a test message via Telegram API...\n";
$testMessage = "✅ This is a test message from the new `diag.php` script. If you received this, your Telegram Bot Token and Admin Chat ID are correct, and the PHP code can successfully send messages.";

if (sendTelegramMessage($adminChatId, $testMessage)) {
    echo "  [SUCCESS] Test message sent to your Telegram admin account.\n";
    echo "  If you received the message, the core PHP application is fully functional.\n\n";
} else {
    echo "  [FAILURE] Failed to send Telegram message.\n";
    echo "  This likely means your TELEGRAM_BOT_TOKEN is invalid or the bot is blocked from messaging the admin chat ID.\n";
    echo "  Please verify the token and ensure your bot can message the admin account.\n\n";
    exit(1);
}

echo "--- Full Diagnostic Test Completed Successfully! ---\n";
echo "If all steps above passed, the core PHP application is working correctly. The issue MUST lie with the web server configuration (.htaccess or Nginx config) or the webhook setup.\n";

?>