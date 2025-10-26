<?php
require_once __DIR__ . '/../bootstrap.php';

header("Content-Type: application/json; charset=UTF-8");

// --- Session Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized. Please log in to view your emails."]);
    exit;
}

$user_id = $_SESSION['user_id'];

global $db_connection;

try {
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

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_emails.php: " . $e->getMessage());
    echo json_encode(["message" => "An internal server error occurred."]);
} finally {
    if ($db_connection) {
        $db_connection->close();
    }
}
