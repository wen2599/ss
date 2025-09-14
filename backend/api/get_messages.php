<?php
// backend/api/get_messages.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Establish connection to MySQL
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Fetch the last 20 messages, newest first
    $stmt = $pdo->query("SELECT message_json FROM telegram_messages ORDER BY message_id DESC LIMIT 20");
    $messages = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // The messages are stored as JSON strings, so we need to decode them before sending
    $decoded_messages = array_map('json_decode', $messages);

    echo json_encode(['success' => true, 'data' => $decoded_messages]);

} catch (PDOException $e) {
    http_response_code(500);
    // For production, you might want a more generic error message
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve messages.']);
}
?>
