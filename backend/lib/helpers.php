<?php
// backend/lib/helpers.php

/**
 * Sends a JSON response with a specified HTTP status code.
 *
 * @param mixed $data The data to encode as JSON.
 * @param int $status_code The HTTP status code to set.
 */
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Establishes a database connection and returns the connection object.
 *
 * @return mysqli|null The mysqli connection object on success, or null on failure.
 */
function get_db_connection() {
    // The @ suppresses the default PHP warning, allowing for custom error handling.
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // In a real application, you would log this error more robustly.
        // For now, we'll return null to indicate failure.
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }

    return $conn;
}

/**
 * Sends a message to a Telegram chat.
 *
 * @param string|int $chat_id The ID of the chat to send the message to.
 * @param string $message The text of the message to send.
 * @return bool True on success, false on failure.
 */
function send_telegram_message($chat_id, $message) {
    $bot_token = TELEGRAM_BOT_TOKEN;
    if (empty($bot_token) || empty($chat_id)) {
        return false;
    }

    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $payload = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown' // Optional: for formatting
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/json\r\n",
            'content' => json_encode($payload),
            'ignore_errors' => true // Allows reading the response body on failure
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($api_url, false, $context);

    if ($result === false) {
        error_log("Telegram API request failed completely.");
        return false;
    }

    $response_data = json_decode($result, true);
    if (!$response_data['ok']) {
        error_log("Telegram API Error: " . ($response_data['description'] ?? 'Unknown error'));
        return false;
    }

    return true;
}
?>