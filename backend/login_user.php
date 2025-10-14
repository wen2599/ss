<?php

require_once __DIR__ . '/api_header.php';

// --- Enhanced Logging ---
$log_file = __DIR__ . '/../../backend.log';
function write_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] [login_user] " . $message . "\n", FILE_APPEND);
}

// --- Input and Validation ---
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    write_log("Invalid JSON received.");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON received.']);
    exit;
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (empty($email) || empty($password)) {
    write_log("Missing email or password.");
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required.']);
    exit;
}

// --- Database and Authentication ---
$pdo = get_db_connection();
if (!$pdo) {
    write_log("Database connection failed.");
    http_response_code(503);
    echo json_encode(['error' => 'Database connection is currently unavailable.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // --- Session Creation ---
        session_regenerate_id(true); // Regenerate session ID for security
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];

        // Log the successful login and session details
        $session_id = session_id();
        write_log("Login successful for user ID: {$user['id']}. Session ID set to: {$session_id}.");

        http_response_code(200);
        echo json_encode([
            'message' => 'Login successful!',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username']
            ]
        ]);

    } else {
        write_log("Invalid login attempt for email: {$email}.");
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Invalid email or password.']);
    }

} catch (PDOException $e) {
    write_log("Database error during login: " . $e->getMessage());
    error_log("User login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal database error occurred.']);
}

?>