<?php
// File: backend/api/register.php
// Description: API endpoint for new user registration.

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

// --- Response Helper ---
function json_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method Not Allowed']);
}

$raw_data = file_get_contents('php://input');
$request_data = json_decode($raw_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    json_response(400, ['success' => false, 'error' => 'Invalid JSON payload.']);
}

// --- Input Validation ---
$username = $request_data['username'] ?? null;
$email = $request_data['email'] ?? null;

if (empty($username) || empty($email)) {
    json_response(400, ['success' => false, 'error' => 'Username and email are required.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(400, ['success' => false, 'error' => 'Invalid email format.']);
}

// --- Database Operations ---
try {
    $conn = get_db_connection();
} catch (Exception $e) {
    error_log("API Register - DB connection failed: " . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Database connection failed.']);
}

// Check if username or email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$stmt->bind_param('ss', $username, $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    json_response(409, ['success' => false, 'error' => 'Username or email already exists.']);
}
$stmt->close();

// Insert the new user (password_hash is intentionally left NULL)
$sql = "INSERT INTO users (username, email) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("API Register - DB statement prepare failed: " . $conn->error);
    json_response(500, ['success' => false, 'error' => 'Database statement preparation failed.']);
}

$stmt->bind_param('ss', $username, $email);

if ($stmt->execute()) {
    $new_user_id = $stmt->insert_id;
    json_response(201, [
        'success' => true, 
        'message' => 'User registered successfully! Emails sent to this address will now be stored.',
        'user_id' => $new_user_id
    ]);
} else {
    error_log("API Register - DB execute failed: " . $stmt->error);
    json_response(500, ['success' => false, 'error' => 'Failed to register user.']);
}

$stmt->close();
$conn->close();

?>
