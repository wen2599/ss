<?php
// 1. Start Session
session_start();

// 2. Include Configuration
require_once __DIR__ . '/config.php';

// 3. Set Headers
header('Content-Type: application/json');

// 4. Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed.']);
    exit();
}

// 5. Get and Decode POST data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 6. Validate Input
if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid or missing email.']);
    exit();
}
if (!isset($data['password']) || empty($data['password'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Password is required.']);
    exit();
}

$email = $data['email'];
$password = $data['password'];

// 7. Database Interaction
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // a. Find user by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // b. Verify user and password
    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, start the session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        // 8. Send Success Response with user data
        http_response_code(200); // OK
        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email']
            ]
        ]);
        exit();
    } else {
        // Bad credentials
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
        exit();
    }

} catch (PDOException $e) {
    error_log("Login DB error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'A server error occurred during login.']);
    exit();
}
?>
