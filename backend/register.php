<?php
// 1. Include Configuration & Set Headers
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// 2. Handle POST request & Decode Data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed.']);
    exit();
}
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 3. Validate Input
if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing email.']);
    exit();
}
if (!isset($data['password']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password is required.']);
    exit();
}

$email = $data['email'];
$password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

// 4. Database Interaction
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Insert new user with only email and password. Username will be NULL.
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, password) VALUES (:email, :password)"
    );
    $stmt->execute([
        ':email' => $email,
        ':password' => $password_hash
    ]);

    // 5. Send Success Response
    http_response_code(201); // Created
    echo json_encode(['success' => true, 'message' => 'User registered successfully.']);

} catch (PDOException $e) {
    // Check for duplicate email error
    if ($e->errorInfo[1] == 1062) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'error' => 'User with this email already exists.']);
    } else {
        error_log("Registration DB error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'A server error occurred during registration.']);
    }
    exit();
}
?>
