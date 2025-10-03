<?php
/**
 * Action: login
 *
 * This script handles user authentication. It validates credentials, checks if the
 * user's account is approved, and creates a session upon successful login.
 *
 * HTTP Method: POST
 *
 * Request Body (JSON):
 * - "email" (string): The user's email address.
 * - "password" (string): The user's password.
 *
 * Response:
 * - On success: { "success": true, "message": "Login successful.", "user": { "id": int, "email": string, "username": string } }
 * - On error: { "success": false, "error": "Error message." }
 */

// The main router (index.php) handles initialization.
// Global variables $pdo and $log are available.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $log->warning("Method not allowed for login.", ['method' => $_SERVER['REQUEST_METHOD']]);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

// 1. Validation: Check for required fields.
if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !isset($data['password'])) {
    http_response_code(400); // Bad Request
    $log->warning("Bad request to login: email or password missing/invalid.", ['data' => $data]);
    echo json_encode(['success' => false, 'error' => 'A valid email and password are required.']);
    exit();
}

$email = $data['email'];
$password = $data['password'];

// 2. Database and Authentication Logic
try {
    $stmt = $pdo->prepare("SELECT id, email, username, password, status FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify password and user status
    if ($user && password_verify($password, $user['password'])) {
        // Check if the account has been approved by an admin.
        if ($user['status'] !== 'approved') {
            http_response_code(403); // Forbidden
            $log->warning("Login attempt for non-approved account.", ['email' => $email, 'status' => $user['status']]);
            echo json_encode(['success' => false, 'error' => '您的账户正在等待管理员批准。']);
            exit();
        }

        // Regenerate session ID to prevent session fixation attacks.
        session_regenerate_id(true);

        // Set session variables.
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['username'] = $user['username']; // Store username in session

        http_response_code(200);
        $log->info("User logged in successfully.", ['user_id' => $user['id'], 'email' => $user['email']]);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'user' => ['id' => $user['id'], 'email' => $user['email'], 'username' => $user['username']]
        ]);
    } else {
        // Invalid credentials. Use a generic error message to avoid user enumeration.
        http_response_code(401); // Unauthorized
        $log->warning("Failed login attempt.", ['email' => $email]);
        echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
    }
} catch (PDOException $e) {
    // The global exception handler in init.php will catch this.
    $log->error("Database error during login.", ['email' => $email, 'error' => $e->getMessage()]);
    throw $e;
}
?>