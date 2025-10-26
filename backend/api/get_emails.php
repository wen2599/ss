<?php
require_once __DIR__ . '/../bootstrap.php';

header("Content-Type: application/json; charset=UTF-8");

$email = $_GET['email'] ?? null;

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["message" => "A valid email address is required."]);
    exit;
}

global $db_connection;

try {
    // First, find the user_id for the given email
    $stmt = $db_connection->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement for user lookup: " . $db_connection->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $user_id = $user['id'];
        $stmt->close();

        // Now, fetch all emails for that user_id
        $stmt = $db_connection->prepare("SELECT id, from_address, subject, body, created_at FROM emails WHERE user_id = ? ORDER BY created_at DESC");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for email lookup: " . $db_connection->error);
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $emails = [];
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row;
        }

        http_response_code(200);
        echo json_encode($emails);
        $stmt->close();

    } else {
        http_response_code(404);
        echo json_encode(["message" => "No user found with that email address."]);
        $stmt->close();
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_emails.php: " . $e->getMessage());
    echo json_encode(["message" => "An internal server error occurred."]);
} finally {
    if ($db_connection) {
        $db_connection->close();
    }
}
