<?php

require_once 'db_operations.php';
require_once 'jwt_handler.php'; // Include the JWT handler

// --- Setup Headers ---
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For development. Restrict in production.
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow Authorization header

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// --- Input and Validation ---
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON received.']);
    exit;
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required.']);
    exit;
}

// --- Database and Authentication ---
$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['error' => 'Database connection is currently unavailable.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // --- JWT Generation ---
        $payload = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ];

        $token = generate_jwt($payload);

        http_response_code(200);
        echo json_encode([
            'message' => 'Login successful!',
            'token' => $token
        ]);

    } else {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Invalid email or password.']);
    }

} catch (PDOException $e) {
    error_log("User login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal database error occurred.']);
}

?>
