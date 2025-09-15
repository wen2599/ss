<?php
// backend/api/register.php
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request headers: " . json_encode(getallheaders()));
require_once 'config.php';
require_once 'db_connect.php';

header('Content-Type: application/json');
// Handle CORS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *'); // Or specify your frontend origin
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed.']);
    exit;
}

// --- Input Validation ---
$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$phone = $data['phone'] ?? '';

if (empty($username) || empty($password) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username, password, and phone number are required.']);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username must be 3-20 characters long and can only contain letters, numbers, and underscores.']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}
if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format.']);
    exit;
}

$conn = db_connect();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // --- Check if username or phone already exists ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR phone = ?");
    $stmt->bind_param("ss", $username, $phone);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        http_response_code(409); // 409 Conflict
        echo json_encode(['success' => false, 'message' => 'Username or phone number already taken.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // --- Hash Password and Insert User ---
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, phone) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $password_hash, $phone);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Registration successful.']);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
}

$conn->close();
?>
