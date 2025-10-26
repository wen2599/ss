<?php
// backend/api/save-email.php

// --- CORS & Content-Type Configuration ---
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Bootstrap application and helpers ---
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/jwt_helper.php';

// --- Main Logic ---

// 1. Verify Authentication Token (JWT)
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$auth_header) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authorization header missing.']);
    exit();
}

// The token is expected to be in "Bearer <token>" format
$token = str_replace('Bearer ', '', $auth_header);

try {
    $decoded_payload = JWT::decode($token, getenv('JWT_SECRET'));
    // Extract user ID from the token payload
    $user_id = $decoded_payload['data']['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('Invalid token payload: user_id missing.');
    }
} catch (Exception $e) {
    http_response_code(403);
    error_log("JWT Validation Failed in save-email.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or expired token.']);
    exit();
}

// 2. Get and Validate Input Data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed. Please use POST.']);
    exit();
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$subject = $data['subject'] ?? null;
$content = $data['content'] ?? null;

// Basic validation
if (empty($subject) || empty($content)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: Subject and content cannot be empty.']);
    exit();
}

// 3. Insert Data into Database
global $db_connection;

// I am assuming the table is named `emails` and has columns: `user_id`, `subject`, `content`.
$query = "INSERT INTO emails (user_id, subject, content, created_at) VALUES (?, ?, ?, NOW())";

$stmt = $db_connection->prepare($query);
if (!$stmt) {
    http_response_code(500);
    error_log("DB Prepare Error in save-email.php: " . $db_connection->error);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: Could not prepare statement.']);
    exit();
}

$stmt->bind_param("iss", $user_id, $subject, $content);

if ($stmt->execute()) {
    http_response_code(201); // 201 Created is a good practice for successful resource creation
    echo json_encode(['status' => 'success', 'message' => 'Email saved successfully.']);
} else {
    http_response_code(500);
    error_log("DB Execute Error in save-email.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: Could not save the email.']);
}

$stmt->close();
$db_connection->close();

?>