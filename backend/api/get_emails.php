<?php
// backend/api/get_emails.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php';
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';

// --- Token Validation Function ---
function validate_token($conn) {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';

    if (preg_match('/Bearer\s((.*)\.(.*)\.(.*))/', $auth_header, $matches)) {
        $token_string = $matches[1];

        $stmt = $conn->prepare("SELECT user_id FROM tokens WHERE token = ? AND expires_at > NOW()");
        if ($stmt) {
            $stmt->bind_param("s", $token_string);
            $stmt->execute();
            $result = $stmt->get_result();
            $token_data = $result->fetch_assoc();
            $stmt->close();

            if ($token_data) {
                return $token_data['user_id']; // Return user_id if token is valid
            }
        }
    }
    return null; // Return null if token is invalid or not found
}

$conn = null;
$user_id = null;

try {
    $conn = get_db_connection();
    if (!$conn) {
        throw new Exception("Database connection failed.");
    }

    // Try to get user_id from session first (for existing session behavior)
    session_start();
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    } else {
        // If not in session, validate token from header
        $user_id = validate_token($conn);
    }

    if (!$user_id) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Unauthorized: 您必须登录才能查看邮件。']);
        exit;
    }

    $emails = [];
    $sql = "SELECT id, from_address, subject, received_at FROM emails WHERE user_id = ? ORDER BY received_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Failed to prepare the SQL statement: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception("Database query failed: " . $stmt->error);
    }

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $emails[] = $row;
        }
    }

    $stmt->close();
    echo json_encode(['success' => true, 'data' => $emails]);

} catch (Exception $e) {
    error_log("API Error in get_emails.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);

} finally {
    if ($conn) {
        $conn->close();
    }
}
