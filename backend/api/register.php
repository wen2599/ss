<?php
// backend/api/register.php
require_once 'config.php';
require_once 'db_connect.php';
header('Content-Type: application/json');

// --- Input Validation ---
$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
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

$conn = db_connect();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // --- Check if username already exists ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        http_response_code(409); // 409 Conflict
        echo json_encode(['success' => false, 'message' => 'Username already taken.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // --- Hash Password and Insert User ---
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $password_hash);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Registration successful.']);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
}

$conn->close();
?>
