<?php
// backend/api/login.php
require_once 'config.php';
require_once 'db_connect.php';
header('Content-Type: application/json');

// It's crucial to start the session before any output.
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
session_start();

// --- Input Validation ---
$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

$conn = db_connect();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // --- Fetch user from database ---
    $stmt = $conn->prepare("SELECT id, username, password_hash, phone, points FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // --- Verify password and create session ---
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['points'] = $user['points'];

        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'phone' => $user['phone'],
                'points' => $user['points']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
}

$conn->close();
?>
