<?php

// A temporary diagnostic script to test the application's core initialization.

// Enable error logging to a specific file for this test
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Use a distinct log file to isolate the output from this test
ini_set('error_log', __DIR__ . '/diagnostic_debug.log');
error_reporting(E_ALL);

// Log that the diagnostic script has been triggered
error_log("DIAGNOSTIC_SCRIPT: check_webhook_init.php was triggered.");

try {
    // Attempt to include the main configuration file.
    // This is the most likely point of failure. If there's a parse error in config.php
    // or any of the files it includes, it will be caught here.
    error_log("DIAGNOSTIC_SCRIPT: Attempting to include config.php...");
    require_once __DIR__ . '/config.php';
    error_log("DIAGNOSTIC_SCRIPT: SUCCESS - config.php and all its dependencies were included without a fatal error.");

    // If we reach this point, the core application environment is likely okay.
    // We can send a test message to confirm the script is running.
    $chat_id_from_update = json_decode(file_get_contents('php://input'))->message->chat->id ?? null;
    if ($chat_id_from_update) {
        $bot_token = getenv('TELEGRAM_BOT_TOKEN');
        if ($bot_token) {
            $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
            $message_data = [
                'chat_id' => $chat_id_from_update,
                'text' => '✅ Diagnostic script ran successfully. The core configuration is OK.'
            ];
            // Use cURL to send the message
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            error_log("DIAGNOSTIC_SCRIPT: Sent confirmation message to Telegram. Result: " . $result);
        } else {
            error_log("DIAGNOSTIC_SCRIPT: TELEGRAM_BOT_TOKEN is not set. Cannot send confirmation message.");
        }
    }

} catch (Throwable $e) {
    // If any error (including parse errors) occurs during the inclusion of config.php,
    // it will be caught and logged here. This is the key to finding the root cause.
    error_log("DIAGNOSTIC_SCRIPT: FATAL ERROR CAUGHT - " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

// Respond to Telegram to prevent retries.
http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Diagnostic check complete.']);

?>