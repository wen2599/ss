<?php
// backend/bot.php
// Telegram Bot Webhook Endpoint

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/handlers.php';

// --- Debug Logging ---
function write_log($message) {
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date("Y-m-d H:i:s");
    // Use FILE_APPEND to add content to the end of the file
    // Use LOCK_EX to prevent concurrent writes
    file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND | LOCK_EX);
}

// --- Main Webhook Logic ---

// Get the raw POST data from the request
$update_json = file_get_contents('php://input');

// Log the raw incoming update for debugging
write_log("Received update: " . $update_json);

// Decode the JSON update
$update = json_decode($update_json, true);

// Check if the update is valid
if (!$update) {
    write_log("Failed to decode JSON update.");
    // Respond immediately to prevent Telegram from resending
    http_response_code(200);
    exit;
}

// At this point, we have a valid update.
// We can now pass it to a handler function.
// The actual processing logic will be in `handlers.php`.
try {
    handle_telegram_update($update);
    write_log("Update processed successfully.");
} catch (Exception $e) {
    // Log any errors that occur during processing
    write_log("Error processing update: " . $e->getMessage());
}

// Always send a 200 OK response to Telegram to acknowledge receipt of the update.
// This prevents Telegram from re-sending the same update, which could cause duplicate entries.
http_response_code(200);
echo "OK";
