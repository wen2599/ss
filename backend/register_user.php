<?php
require_once __DIR__ . '/api_header.php';

write_log("------ register_user.php Entry Point ------");

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON received.']);
    exit;
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;
$username = $email;

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields: email and password.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
    exit;
}

$pdo = get_db_connection();
if (is_array($pdo) && isset($pdo['db_error'])) {
    write_log("Failed to get database connection in register_user.php: " . $pdo['db_error']);
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Database connection is currently unavailable.']);
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'A user with this email already exists.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $isSuccess = $stmt->execute([$username, $email, $password_hash]);

    if ($isSuccess) {
        $user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;

        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'User registered successfully.',
            'user' => [
                'id' => $user_id,
                'email' => $email,
                'username' => $username
            ]
        ]);
    } else {
        write_log("Failed to register user " . $email . " due to server issue.");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to register the user due to a server issue.']);
    }

} catch (PDOException $e) {
    write_log("User registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An internal database error occurred.']);
}

write_log("------ register_user.php Exit Point ------");

?>
