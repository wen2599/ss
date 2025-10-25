<?php
// --- Bootstrap aplication ---
require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/api/parse_email.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents("php://input");
    $emailData = json_decode($rawData, true);

    if (isset($emailData['from']) && isset($emailData['subject']) && isset($emailData['body'])) {
        $from = $emailData['from'];
        $subject = $emailData['subject'];
        $body = $emailData['body'];

        $userId = find_user_by_email($from);

        if ($userId) {
            $extractedData = parse_email_body($body);
            save_email($userId, $from, $subject, $body, $extractedData);
            http_response_code(200);
            echo json_encode(["message" => "Email processed and saved for user {$userId}."]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User with email '{$from}' not found. Email not saved."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Invalid email data. 'from', 'subject', and 'body' are required."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed. Please use POST."]);
}

function find_user_by_email($email) {
    global $db_connection;
    $stmt = $db_connection->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        return $user['id'];
    }
    $stmt->close();
    return null;
}

function save_email($userId, $from, $subject, $body, $extractedData) {
    global $db_connection;
    $jsonExtractedData = json_encode($extractedData);
    $stmt = $db_connection->prepare("INSERT INTO emails (user_id, from_address, subject, body, extracted_data) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $from, $subject, $body, $jsonExtractedData);
    $stmt->execute();
    $stmt->close();
}
