<?php
// 1. Include Configuration
require_once __DIR__ . '/config.php';

// 2. Set Headers
header('Content-Type: application/json');

// 3. Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed.']);
    exit();
}

// 4. Get and Decode POST data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 5. Validate Input
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
if (strlen($data['password']) < 8) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters long.']);
    exit();
}

$email = $data['email'];
$password = $data['password'];

// 6. Hash Password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 7. Database Interaction
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // a. Check if user already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'error' => 'User with this email already exists.']);
        exit();
    }

    // b. Insert new user
    // Note: Assumes the 'username' column stores the email.
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (:email, :password_hash)");
    $stmt->execute([':email' => $email, ':password_hash' => $password_hash]);

    // 8. Send Success Response
    http_response_code(201); // Created
    echo json_encode(['success' => true, 'message' => 'User registered successfully.']);

} catch (PDOException $e) {
    error_log("Registration DB error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'A server error occurred during registration.']);
    exit();
}
?>
