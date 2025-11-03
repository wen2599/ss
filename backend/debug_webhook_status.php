<?php
// --- Webhook Debugger (Sends to Telegram Admin) ---

// Load environment variables
require_once __DIR__ . '/utils/config_loader.php';

// Get required environment variables
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$admin_id = getenv('TELEGRAM_ADMIN_ID');

/**
 * Sends a message to a specific Telegram chat.
 *
 * @param int|string $chat_id The ID of the chat.
 * @param string $text The message text.
 * @param string $bot_token The bot token.
 */
function sendMessage($chat_id, $text, $bot_token) {
    // URL encode the text
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown' // Optional: for better formatting
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Don't output the response of the sendMessage call
    curl_exec($ch);
    curl_close($ch);
}

// --- Main Logic ---

// Always respond to Telegram first to prevent timeouts and retries
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Request is being processed.']);

// Check if critical environment variables are set
if (!$bot_token || !$admin_id) {
    // If they aren't set, we can't send a message, so just exit silently.
    // An error will have already been logged by the config_loader if it's missing.
    exit;
}

// --- Capture Request Data ---
$raw_input = file_get_contents('php://input');
$headers = getallheaders();
$request_info = [
    'Remote IP' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    'Request'   => ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . ' ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'),
    'Timestamp' => date('Y-m-d H:i:s T')
];

// --- Format and Send Debug Message ---
$message = "*Webhook Debug Info*\n\n";
$message .= "```json\n" . json_encode($request_info, JSON_PRETTY_PRINT) . "\n```\n\n";
$message .= "*Headers*\n";
$message .= "```json\n" . json_encode($headers, JSON_PRETTY_PRINT) . "\n```\n\n";
$message .= "*Raw Body*\n";
$message .= "```\n" . ($raw_input ?: '(empty)') . "\n```";

// Telegram has a message size limit of 4096 characters. Truncate if necessary.
if (strlen($message) > 4096) {
    $message = substr($message, 0, 4090) . "\n... (truncated)";
}

// Send the debug information to the admin
sendMessage($admin_id, $message, $bot_token);

?>
