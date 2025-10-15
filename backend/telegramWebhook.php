<?php

// --- Enhanced Trace Logging ---
// A simple function to log each step of the execution to a dedicated trace file.
function trace_log($message) {
    // Prepend a timestamp to each message for clarity.
    $timestamp = date('Y-m-d H:i:s');
    // Use FILE_APPEND to add to the log file without overwriting it.
    file_put_contents(__DIR__ . '/webhook_trace.log', "[$timestamp] - " . $message . "\n", FILE_APPEND);
}

// Clear the log for each new request to keep it clean.
file_put_contents(__DIR__ . '/webhook_trace.log', '');

trace_log("--- STEP 0: Webhook script started. ---");

// Set up error reporting for debugging.
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log'); // Continue using the main debug log for errors.

trace_log("--- STEP 1: Loading core files. ---");
// Load the core configuration and helper functions.
require_once __DIR__ . '/config.php';
trace_log("`config.php` loaded successfully.");

// --- Security Check ---
trace_log("--- STEP 2: Performing security check. ---");
$secretTokenFromEnv = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
trace_log("Secret from .env: " . (empty($secretTokenFromEnv) ? "NOT SET" : "Set"));
trace_log("Secret from Header: " . (empty($receivedToken) ? "NOT RECEIVED" : "Received"));

if (empty($secretTokenFromEnv) || $receivedToken !== $secretTokenFromEnv) {
    trace_log("--- ❌ FAILURE: Security token mismatch or not configured. Exiting. ---");
    http_response_code(403);
    exit('Forbidden');
}
trace_log("--- STEP 2a: Security check passed. ---");

// --- Process Incoming Update ---
trace_log("--- STEP 3: Reading raw input from php://input. ---");
$raw_input = file_get_contents('php://input');
if (empty($raw_input)) {
    trace_log("--- ⚠️ WARNING: Raw input was empty. Exiting gracefully. ---");
    http_response_code(200);
    exit();
}
trace_log("--- STEP 3a: Raw input received (Length: " . strlen($raw_input) . " bytes). ---");
trace_log("Input Data: " . $raw_input);


trace_log("--- STEP 4: Decoding JSON update. ---");
$update = json_decode($raw_input, true);
if ($update === null) {
    trace_log("--- ❌ FAILURE: Failed to decode JSON. Exiting. ---");
    http_response_code(400); // Bad Request
    exit();
}
trace_log("--- STEP 4a: JSON decoded successfully. ---");

// --- Extract Key Information ---
trace_log("--- STEP 5: Checking for 'message' object and extracting data. ---");
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $command = trim($message['text'] ?? '');
    trace_log("Data extracted: ChatID={$chatId}, UserID={$userId}, Command='{$command}'");

    // --- Admin Verification ---
    trace_log("--- STEP 6: Verifying admin user. ---");
    $adminUserId = getenv('TELEGRAM_ADMIN_ID');
    trace_log("Admin ID from .env: " . (empty($adminUserId) ? "NOT SET" : $adminUserId));
    if (empty($adminUserId) || (string)$userId !== (string)$adminUserId) {
        trace_log("--- ❌ FAILURE: User {$userId} is not the authorized admin. Exiting. ---");
        http_response_code(200); // Respond OK to Telegram but do nothing.
        exit();
    }
    trace_log("--- STEP 6a: Admin verified. ---");

    // --- Command Processing ---
    if (!empty($command)) {
        trace_log("--- STEP 7: Calling processCommand(). ---");
        try {
            processCommand($chatId, $userId, $command);
            trace_log("--- ✅ SUCCESS: processCommand() completed without throwing an exception. ---");
        } catch (Throwable $e) {
            trace_log("--- ❌ FATAL ERROR in processCommand(): " . $e->getMessage() . " ---");
            // Log the full error to the main debug log
            error_log("FATAL ERROR in processCommand(): " . $e->getMessage() . "\n" . $e->getTraceAsString());
            sendTelegramMessage($chatId, "An error occurred while processing your command.");
        }
    } else {
        trace_log("--- ⚠️ WARNING: Command was empty. Nothing to process. ---");
    }
} else {
    trace_log("--- ⚠️ WARNING: Update did not contain a 'message' object. Nothing to do. ---");
}

trace_log("--- SCRIPT FINISHED ---");
// Acknowledge receipt to Telegram's server to prevent webhook retries.
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>