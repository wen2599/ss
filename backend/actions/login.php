<?php
// Action: Log in a user

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed for login.']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input. Email and password are required.']);
    exit();
}

$email = $data['email'];
$password = $data['password'];

try {
    // The $pdo variable is inherited from index.php
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'user' => ['id' => $user['id'], 'email' => $user['email']]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
    }
} catch (PDOException $e) {
    error_log("Login DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A server error occurred during login.']);
}
?>
