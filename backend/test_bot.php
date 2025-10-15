<?php

// --- Standalone Bot Health Check Script ---
// This script is designed to be run from the command line (CLI)
// to diagnose the health of the bot's core components.
// Usage: php test_bot.php

echo "--- Starting Bot Health Check ---\n\n";

// --- Step 1: Set up the environment ---
// Mimic the web environment for consistent execution.
// We need to be in the 'backend' directory.
chdir(__DIR__);
echo "[1] Current Directory: " . getcwd() . "\n";

// --- Step 2: Load Core Configuration ---
// This is the most critical step. If this fails, the script will crash.
echo "[2] Loading config.php...\n";
try {
    require_once __DIR__ . '/config.php';
    echo "    ✅ config.php loaded successfully.\n\n";
} catch (Throwable $e) {
    echo "    ❌ FATAL ERROR: Failed to load config.php.\n";
    echo "       Error: " . $e->getMessage() . "\n";
    echo "       This is likely a PHP syntax error (e.g., missing semicolon, mismatched brackets) in one of the core files.\n";
    exit(1); // Exit with an error code
}

// --- Step 3: Check for Critical Functions ---
echo "[3] Verifying essential functions...\n";
if (function_exists('processCommand')) {
    echo "    ✅ processCommand() function exists.\n";
} else {
    echo "    ❌ CRITICAL: processCommand() function is NOT defined. The bot cannot process any commands.\n";
}

if (function_exists('sendTelegramMessage')) {
    echo "    ✅ sendTelegramMessage() function exists.\n";
} else {
    echo "    ❌ CRITICAL: sendTelegramMessage() function is NOT defined. The bot cannot send messages.\n";
}
echo "\n";


// --- Step 4: Check Database Connection ---
echo "[4] Testing database connection...\n";
$pdo = get_db_connection();
if ($pdo) {
    echo "    ✅ Database connection successful.\n";
    try {
        $stmt = $pdo->query("SELECT 1");
        echo "    ✅ Successfully executed a simple query.\n";
    } catch (PDOException $e) {
        echo "    ❌ ERROR: Connected to database, but query failed.\n";
        echo "       Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "    ❌ FAILED to connect to the database. Check your .env credentials and MySQL server status.\n";
}
echo "\n";


// --- Step 5: Send a Diagnostic Message to Telegram ---
echo "[5] Attempting to send a status message to Telegram...\n";
$adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
$botToken = getenv('TELEGRAM_BOT_TOKEN');

if (!$adminChatId || !$botToken) {
    echo "    ❌ SKIPPED: TELEGRAM_ADMIN_CHAT_ID or TELEGRAM_BOT_TOKEN not set in .env file.\n";
} else {
    $testMessage = "✅ **Bot Health Check Complete**\n\n";
    $testMessage .= "This message was sent from the `test_bot.php` script.\n";
    $testMessage .= "If you are seeing this, it means the core configuration, helper functions, and the `sendTelegramMessage` function are all working correctly.";

    if (sendTelegramMessage($adminChatId, $testMessage)) {
        echo "    ✅ Successfully sent a test message to the admin chat ID.\n";
    } else {
        echo "    ❌ FAILED to send a test message. Check bot token, admin chat ID, and cURL extension.\n";
    }
}

echo "\n--- Health Check Finished ---\n";

?>