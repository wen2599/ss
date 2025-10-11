
<?php

// --- Enhanced Error Handling & Logging ---
// This section is crucial for debugging. It ensures that ANY error,
// even fatal ones, get written to the server's error log instead of failing silently.
ini_set('display_errors', 0); // NEVER display errors to the public
ini_set('log_errors', 1);     // ALWAYS log errors
error_reporting(E_ALL);

// Global Exception Handler: Catches any error that isn't caught elsewhere.
set_exception_handler(function($exception) {
    error_log("!!! UNCAUGHT EXCEPTION !!!\nFile: " . $exception->getFile() . "\nLine: " . $exception->getLine() . "\nMessage: " . $exception->getMessage() . "\nTrace: " . $exception->getTraceAsString());
    // We can't reply to the user here as the script is unstable, so we just log and exit.
    exit();
});

error_log("--- [Webhook START] --- Script was triggered.");

// --- File Includes ---
// We will log the outcome of each `require_once` to ensure all files are loaded correctly.
try {
    require_once dirname(__DIR__) . '/config.php';
    error_log("[OK] config.php loaded successfully.");

    require_once dirname(__DIR__) . '/core/Database.php';
    error_log("[OK] Database.php loaded successfully.");

    require_once dirname(__DIR__) . '/core/Telegram.php';
    error_log("[OK] Telegram.php loaded successfully.");
} catch (Throwable $t) {
    error_log("!!! FATAL: Failed to include a required file. Error: " . $t->getMessage());
    exit(); // Stop immediately if a core file is missing.
}

// --- Main Logic ---
// Get the raw POST data from the Telegram request. This is the most critical piece of data.
$json = file_get_contents('php://input');
if ($json === false) {
    error_log("!!! CRITICAL: file_get_contents('php://input') failed. No data received.");
    exit();
}
if (empty($json)) {
    error_log("--- [Webhook END] --- Received an empty request. This is normal. Exiting.");
    exit();
}

// Log the raw, untouched data from Telegram.
error_log("Received Raw Update from Telegram:\n" . $json);

// Decode the JSON data into a PHP array
$update = json_decode($json, true);

if (!$update) {
    error_log("!!! CRITICAL: Failed to decode JSON. The received data may be malformed.");
    exit;
}

error_log("JSON decoded successfully. Update ID: " . ($update['update_id'] ?? 'Not found'));

// --- Route Based on Update Type ---
// We wrap the routing logic in a try-catch block to handle any errors during processing.
try {
    // 1. Handle posts from the lottery channel
    if (isset($update['channel_post'])) {
        error_log("Routing to: handleChannelPost");
        handleChannelPost($update['channel_post']);
        error_log("Finished: handleChannelPost");
        exit;
    }

    // 2. Handle direct messages from users
    if (isset($update['message'])) {
        error_log("Routing to: handleUserMessage");
        handleUserMessage($update['message']);
        error_log("Finished: handleUserMessage");
        exit;
    }

    error_log("No 'channel_post' or 'message' key found in the update. Nothing to do.");

} catch (Throwable $e) {
    // This will catch errors happening inside handleChannelPost or handleUserMessage
    error_log("!!! FATAL ERROR during routing/handling !!!\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\nMessage: " . $e->getMessage());
}

error_log("--- [Webhook END] --- Script finished.");


// --- Function Definitions (remain the same) ---

function handleChannelPost(array $post): void {
    if (($post['chat']['id'] ?? null) != TELEGRAM_CHANNEL_ID) {
        error_log("Ignoring post from unauthorized channel: " . ($post['chat']['id'] ?? 'N/A'));
        return;
    }
    // ... rest of the function ...
}

function handleUserMessage(array $message): void {
    // ... function content ...
}

?>
