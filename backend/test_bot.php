<?php

header('Content-Type: text/plain; charset=utf-8');

echo "Starting Bot Diagnostic Test...\n";
echo "=================================\n\n";

// --- Step 1: Include Configuration ---
echo "Step 1: Loading config.php...\n";
// Use a try-catch block to gracefully handle fatal errors during inclusion
try {
    require_once __DIR__ . '/config.php';
    echo "✅ config.php loaded successfully.\n\n";
} catch (Throwable $e) {
    echo "❌ FATAL ERROR: Failed to load config.php.\n";
    echo "Error Message: " . $e->getMessage() . "\n";
    echo "This is a critical failure. The application cannot run. Please check the PHP syntax and file permissions for all included files.\n";
    exit;
}

// --- Step 2: Check Environment Variables ---
echo "Step 2: Checking for essential environment variables...\n";
$required_vars = [
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'TELEGRAM_BOT_TOKEN',
    'TELEGRAM_ADMIN_CHAT_ID'
];

$all_vars_found = true;
foreach ($required_vars as $var) {
    $value = getenv($var);
    if ($value === false || $value === '') {
        echo "   ❌ Missing or empty: {$var}\n";
        $all_vars_found = false;
    } else {
        // Mask sensitive values for security
        $display_value = ($var === 'DB_PASS' || $var === 'TELEGRAM_BOT_TOKEN') ? '********' : $value;
        echo "   ✅ Found: {$var} = {$display_value}\n";
    }
}

if ($all_vars_found) {
    echo "✅ All essential environment variables are present.\n\n";
} else {
    echo "❌ One or more environment variables are missing. Please check your `.env` file and ensure it is being loaded correctly.\n\n";
}


// --- Step 3: Test Database Connection ---
echo "Step 3: Testing database connection...\n";
if (function_exists('get_db_connection')) {
    try {
        $conn = get_db_connection();
        if ($conn) {
            echo "✅ Database connection successful.\n";
            // Use PDO-specific method to get server version
            echo "   - MySQL Server Version: " . $conn->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
            // Close PDO connection by setting it to null
            $conn = null;
        } else {
            // This case might not be reached if get_db_connection throws an exception
            echo "❌ Database connection failed. The function returned null, but no exception was thrown.\n";
        }
    } catch (Exception $e) {
        echo "❌ Database connection failed with an exception.\n";
        echo "   - Error: " . $e->getMessage() . "\n";
        echo "   - Please verify your DB credentials in the .env file and ensure the MySQL server is running and accessible.\n";
    }
} else {
    echo "❌ Function `get_db_connection()` does not exist. This function should be defined in `db_operations.php`.\n";
}
echo "\n";


// --- Step 4: Test Telegram API ---
echo "Step 4: Testing Telegram API communication...\n";
$admin_chat_id = getenv('TELEGRAM_ADMIN_CHAT_ID');
if (!$admin_chat_id) {
    echo "❌ Cannot test Telegram API. `TELEGRAM_ADMIN_CHAT_ID` is not set.\n";
} elseif (!function_exists('sendTelegramMessage')) {
    echo "❌ Function `sendTelegramMessage()` does not exist. This function should be defined in `telegram_helpers.php`.\n";
} else {
    echo "   - Attempting to send a test message to admin chat ID: {$admin_chat_id}...\n";
    $test_message = "✅ Hello from the diagnostic script! If you received this, the Telegram API connection is working correctly.";

    // Temporarily override the function to avoid using reply markup
    if (sendTelegramMessage($admin_chat_id, $test_message, null)) {
        echo "✅ Test message sent successfully to Telegram.\n";
        echo "   - Please check your Telegram chat to confirm you received the message.\n";
    } else {
        echo "❌ Failed to send test message via Telegram API.\n";
        echo "   - This could be due to an invalid BOT_TOKEN, an incorrect ADMIN_CHAT_ID, or network issues between the server and Telegram.\n";
        echo "   - Check the `debug.log` file for more detailed cURL error messages.\n";
    }
}
echo "\n";

echo "=================================\n";
echo "Diagnostic Test Finished.\n";

?>