<?php
// File: backend/bot/set_gemini_key.php
// Description: Handles the logic for the /setgeminiapikey command.

require_once __DIR__ . '/../utils/send_telegram_message.php';
require_once __DIR__ . '/../config/secrets.php';

/**
 * Handles the /setgeminiapikey command.
 *
 * @param mysqli $conn The database connection.
 * @param int $chat_id The chat ID to send the response to.
 * @param string $text The message text containing the command and the new key.
 */
function handle_set_gemini_api_key($conn, $chat_id, $text) {
    // Split the message text to get the key.
    // Expected format: /setgeminiapikey YOUR_API_KEY
    $parts = explode(' ', $text, 2);

    if (count($parts) < 2 || empty($parts[1])) {
        send_telegram_message($chat_id, "❌ **Invalid format.**\n\nPlease use the format: `/setgeminiapikey YOUR_API_KEY`", ['parse_mode' => 'Markdown']);
        return;
    }

    $new_api_key = trim($parts[1]);

    // Validate the key format (basic validation)
    if (strlen($new_api_key) < 30) { // Gemini keys are typically long
        send_telegram_message($chat_id, "⚠️ **Warning:** The provided key seems a bit short. Please double-check it. If you are sure, send it again preceded by the `/forcesetgeminiapikey` command.");
        return;
    }
    
    // Update the key in the database
    $success = update_system_setting($conn, 'GEMINI_API_KEY', $new_api_key);

    if ($success) {
        // Obfuscate the key for the confirmation message for security
        $obfuscated_key = substr($new_api_key, 0, 4) . '...' . substr($new_api_key, -4);
        $message = "✅ **Success!**\n\nYour Google Gemini API Key has been updated.\nNew Key: `{$obfuscated_key}`";
        send_telegram_message($chat_id, $message, ['parse_mode' => 'Markdown']);
    } else {
        send_telegram_message($chat_id, "❌ **Database Error.**\n\nCould not update the API key in the database. Please check the server logs.");
    }
}

/**
 * Handles the /forcesetgeminiapikey command, bypassing basic validation.
 */
function handle_force_set_gemini_api_key($conn, $chat_id, $text) {
    $parts = explode(' ', $text, 2);
    if (count($parts) < 2 || empty($parts[1])) {
        send_telegram_message($chat_id, "❌ **Invalid format.**\n\nPlease use the format: `/forcesetgeminiapikey YOUR_API_KEY`", ['parse_mode' => 'Markdown']);
        return;
    }
    $new_api_key = trim($parts[1]);

    $success = update_system_setting($conn, 'GEMINI_API_KEY', $new_api_key);

    if ($success) {
        $obfuscated_key = substr($new_api_key, 0, 4) . '...' . substr($new_api_key, -4);
        $message = "✅ **Success!**\n\nYour Google Gemini API Key has been forcefully updated.\nNew Key: `{$obfuscated_key}`";
        send_telegram_message($chat_id, $message, ['parse_mode' => 'Markdown']);
    } else {
        send_telegram_message($chat_id, "❌ **Database Error.**\n\nCould not update the API key.");
    }
}

?>
