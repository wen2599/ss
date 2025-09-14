<?php
// backend/api/webhook_handler.php
require_once 'config.php';

// Get the raw POST data from the request
$json = file_get_contents('php://input');
// Decode the JSON data
$update = json_decode($json, true);

// Check if it's a channel post
if (isset($update['channel_post'])) {
    $message = $update['channel_post'];
    $message_id = $message['message_id'];
    $chat_id = $message['chat']['id'];
    // Re-encode the message part to store as JSON in the DB
    $message_json = json_encode($message, JSON_UNESCAPED_UNICODE);

    try {
        // Establish connection to MySQL
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Prepare SQL statement to prevent SQL injection
        $sql = "INSERT INTO telegram_messages (message_id, chat_id, message_json) VALUES (:message_id, :chat_id, :message_json)";
        $stmt = $pdo->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
        $stmt->bindParam(':chat_id', $chat_id, PDO::PARAM_INT);
        $stmt->bindParam(':message_json', $message_json, PDO::PARAM_STR);

        // Execute the statement
        $stmt->execute();

    } catch (PDOException $e) {
        // If the error is a duplicate entry (error code 23000), we can ignore it
        // as it means we've already processed this message. For other errors,
        // it might be useful to log them.
        if ($e->getCode() !== '23000') {
            // For debugging, you might want to log this error.
            // error_log("Webhook DB Error: " . $e->getMessage());
        }
    }
}

// Always respond with 200 OK to Telegram, even if we don't process the update type.
http_response_code(200);
// It's good practice to send a minimal response body or none at all.
echo json_encode(['status' => 'ok']);
?>
