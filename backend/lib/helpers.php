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
 * Retrieves a specific API key from the database.
 *
 * @param string $service_name The name of the service (e.g., 'gemini').
 * @return string|null The API key or null if not found.
 */
function get_api_key($service_name) {
    $conn = get_db_connection();
    if (!$conn) return null;

    $stmt = $conn->prepare("SELECT api_key FROM api_keys WHERE service_name = ?");
    $stmt->bind_param("s", $service_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['api_key'];
    }
    
    return null;
}

/**
 * Sets or updates a specific API key in the database.
 *
 * @param string $service_name The name of the service (e.g., 'gemini').
 * @param string $api_key The API key to store.
 * @return bool True on success, false on failure.
 */
function set_api_key($service_name, $api_key) {
    $conn = get_db_connection();
    if (!$conn) return false;

    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both cases
    $stmt = $conn->prepare("
        INSERT INTO api_keys (service_name, api_key) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE api_key = ?
    ");
    $stmt->bind_param("sss", $service_name, $api_key, $api_key);
    
    return $stmt->execute();
}


/**
 * Sends a message to a Telegram chat.
 *
 * @param string|int $chat_id The ID of the chat to send the message to.
 * @param string $message The text of the message to send.
 * @return bool True on success, false on failure.
 */
function send_telegram_message($chat_id, $message, $reply_markup = null) {
    $bot_token = TELEGRAM_BOT_TOKEN;
    if (empty($bot_token) || empty($chat_id)) {
        return false;
    }

    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $payload = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    if ($reply_markup) {
        $payload['reply_markup'] = $reply_markup;
    }

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

/**
 * Answers a Telegram callback query.
 * This is used to acknowledge that a button press has been received.
 *
 * @param string $callback_query_id The ID of the callback query to answer.
 * @return bool True on success, false on failure.
 */
function answer_callback_query($callback_query_id) {
    $bot_token = TELEGRAM_BOT_TOKEN;
    if (empty($bot_token) || empty($callback_query_id)) {
        return false;
    }

    $api_url = "https://api.telegram.org/bot{$bot_token}/answerCallbackQuery";

    $payload = [
        'callback_query_id' => $callback_query_id
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/json\r\n",
            'content' => json_encode($payload),
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    file_get_contents($api_url, false, $context); // We don't need to check the response for this

    return true;
}
?>