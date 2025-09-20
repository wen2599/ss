<?php
// backend/api/login.php

require_once __DIR__ . '/database.php';

// --- Session Configuration ---
// It's crucial to configure session cookies for security.
$session_params = [
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'domain' => '', // Set your domain in production
    'secure' => isset($_SERVER['HTTPS']), // Only send over HTTPS
    'httponly' => true, // Prevent client-side script access
    'samesite' => 'Lax' // Or 'Strict'
];
session_set_cookie_params($session_params);
session_start();

// --- CORS and HTTP Method Check ---
header("Access-Control-Allow-Origin: " . ($_ENV['FRONTEND_URL'] ?? 'http://localhost:5173'));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed.']);
    exit;
}

// --- Main Logic ---
$db = null;
try {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    // --- 1. Input Validation ---
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }

    $db = getDbConnection();

    // --- 2. Fetch User ---
    $stmt = $db->prepare("SELECT id, email, password_hash FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // --- 3. Verify Password ---
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // --- 4. Set Session on Successful Login ---
    // Regenerate session ID for security
    session_regenerate_id(true);

    // Store user data in the session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];

    // --- 5. Success Response ---
    http_response_code(200); // OK
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.', 'error' => $e->getMessage()]);
} finally {
    if ($db) {
        $db = null;
    }
}
?>
