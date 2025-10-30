<?php
// File: backend/bot.php
// Description: Main webhook endpoint for the Telegram Admin Bot.

// --- Core Includes ---
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/utils/send_telegram_message.php';
require_once __DIR__ . '/bot/keyboards.php'; // Include our new keyboard layouts

// --- Command Handlers ---
require_once __DIR__ . '/bot/set_gemini_key.php';

// --- Webhook Security and Setup ---
$admin_id = get_telegram_admin_id();
if (!$admin_id) {
    http_response_code(500);
    error_log("CRITICAL: TELEGRAM_ADMIN_ID is not configured.");
    exit();
}

$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

// Check for callback queries (from inline keyboards)
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $callback_data = $callback_query['data'];

    if ((string)$user_id !== (string)$admin_id) { exit(); } // Security check

    // Handle callback data
    if ($callback_data === 'main_menu') {
        send_telegram_message($chat_id, 'Returning to main menu...', ['reply_markup' => get_main_menu_keyboard()]);
    } elseif ($callback_data === 'view_config') {
        // You would fetch and display config here
        send_telegram_message($chat_id, 'Displaying current config...'); 
    } else {
        // Acknowledge callback
        send_telegram_message($chat_id, 'Action not yet implemented.');
    }
    exit();
}

// Check for regular messages
if (!isset($update['message']['chat']['id'])) {
    http_response_code(200);
    exit();
}

$chat_id = $update['message']['chat']['id'];
$user_id = $update['message']['from']['id'];
$text = trim($update['message']['text']);

if ((string)$user_id !== (string)$admin_id) {
    send_telegram_message($chat_id, "Sorry, this is a private bot. Access is denied.");
    error_log("Unauthorized access by user ID: {$user_id}");
    exit();
}

// --- Database Connection ---
try {
    $conn = get_db_connection();
} catch (Exception $e) {
    error_log("Bot.php - DB connection failed: " . $e->getMessage());
    send_telegram_message($chat_id, "? **Critical Error:** DB Connection Failed.");
    exit();
}

// --- Main Command and Text Router ---

// Check for commands starting with '/'
if (strpos($text, '/') === 0) {
    $command_parts = explode(' ', $text, 2);
    $command = $command_parts[0];

    switch ($command) {
        case '/start':
            $welcome_message = "? **Admin Bot Activated**\n\nWelcome, Admin! Please choose an option from the menu below.";
            send_telegram_message($chat_id, $welcome_message, [
                'parse_mode' => 'Markdown',
                'reply_markup' => get_main_menu_keyboard()
            ]);
            break;

        case '/setgeminiapikey':
            handle_set_gemini_api_key($conn, $chat_id, $text);
            break;
        
        case '/forcesetgeminiapikey':
            handle_force_set_gemini_api_key($conn, $chat_id, $text);
            break;
        
        default:
            send_telegram_message($chat_id, "? Unrecognized command.", ['reply_markup' => get_main_menu_keyboard()]);
            break;
    }
} else {
    // Check for keyboard button presses (text without '/')
    switch ($text) {
        case '⚙️ Settings':
            send_telegram_message($chat_id, "Here are your settings options:", ['reply_markup' => get_settings_keyboard()]);
            break;
        
        case '🔑 Update Gemini Key':
            send_telegram_message($chat_id, "Please send your new Gemini API Key in the format:\n`/setgeminiapikey YOUR_API_KEY`", ['parse_mode' => 'Markdown']);
            break;

        // Add cases for other main menu buttons here...
        case '📊 Stats':
        case '👤 Manage Users':
        case '✉️ Authorize Email':
            send_telegram_message($chat_id, "This feature is not yet implemented.");
            break;
        
        default:
            // Default behavior for non-command, non-button text
            send_telegram_message($chat_id, "I'm waiting for a command from the menu. You can also type `/start` to reset.", ['reply_markup' => get_main_menu_keyboard()]);
            break;
    }
}

$conn->close();
http_response_code(200);

?>
