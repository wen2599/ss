
<?php

// --- Enhanced Error Handling & Logging ---
// This section is crucial for debugging. It ensures that ANY error,
// even fatal ones, get written to a log file WE can access.

// REDIRECT ERROR LOGS: All logs will go to /public_html/debug_webhook.log
ini_set('error_log', dirname(__DIR__, 3) . '/debug_webhook.log');

ini_set('display_errors', 0); // NEVER display errors to the public
ini_set('log_errors', 1);     // ALWAYS log errors
error_reporting(E_ALL);

// Global Exception Handler: Catches any error that isn't caught elsewhere.
set_exception_handler(function($exception) {
    error_log("!!! UNCAUGHT EXCEPTION !!!\nFile: " . $exception->getFile() . "\nLine: " . $exception->getLine() . "\nMessage: " . $exception->getMessage() . "\nTrace: " . $exception->getTraceAsString());
    exit();
});

error_log("--- [Webhook START] --- Script was triggered.");

// --- File Includes ---
try {
    require_once dirname(__DIR__) . '/config.php';
    error_log("[OK] config.php loaded successfully.");

    require_once dirname(__DIR__) . '/core/Database.php';
    error_log("[OK] Database.php loaded successfully.");

    require_once dirname(__DIR__) . '/core/Telegram.php';
    error_log("[OK] Telegram.php loaded successfully.");
} catch (Throwable $t) {
    error_log("!!! FATAL: Failed to include a required file. Error: " . $t->getMessage());
    exit();
}

// --- Main Logic ---
$json = file_get_contents('php://input');
if ($json === false) {
    error_log("!!! CRITICAL: file_get_contents('php://input') failed. No data received.");
    exit();
}
if (empty($json)) {
    error_log("--- [Webhook END] --- Received an empty request. This is normal. Exiting.");
    exit();
}

error_log("Received Raw Update from Telegram:\n" . $json);

$update = json_decode($json, true);

if (!$update) {
    error_log("!!! CRITICAL: Failed to decode JSON. The received data may be malformed.");
    exit;
}

error_log("JSON decoded successfully. Update ID: " . ($update['update_id'] ?? 'Not found'));

// --- Routing ---
try {
    if (isset($update['channel_post'])) {
        error_log("Routing to: handleChannelPost");
        handleChannelPost($update['channel_post']);
        error_log("Finished: handleChannelPost");
        exit;
    }

    if (isset($update['message'])) {
        error_log("Routing to: handleUserMessage");
        handleUserMessage($update['message']);
        error_log("Finished: handleUserMessage");
        exit;
    }

    error_log("No 'channel_post' or 'message' key found in the update. Nothing to do.");

} catch (Throwable $e) {
    error_log("!!! FATAL ERROR during routing/handling !!!\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\nMessage: " . $e->getMessage());
}

error_log("--- [Webhook END] --- Script finished.");


// --- Function Definitions ---
// NOTE: The actual function content is not included here for brevity,
// but it remains the same as in the previous version.

