<?php
require_once __DIR__ . '/api_header.php';

write_log("------ login_user.php Entry Point ------");

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON received.']);
    exit;
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
    exit;
}

$pdo = get_db_connection();
if (is_array($pdo) && isset($pdo['db_error'])) {
    write_log("Failed to get database connection in login_user.php: " . $pdo['db_error']);
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Database connection is currently unavailable.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];

        write_log("User logged in successfully: " . $email);

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful!',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username']
            ]
        ]);
    } else {
        write_log("Invalid login attempt for email: " . $email);
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
    }

} catch (PDOException $e) {
    write_log("User login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An internal database error occurred.']);
}

write_log("------ login_user.php Exit Point ------");

?>
