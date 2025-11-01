<?php
function isUserRegistered() {
    $email = $_GET['email'] ?? '';
    if (empty($email)) {
        echo json_encode(['success' => false, 'is_registered' => false, 'message' => 'Email not provided']);
        return;
    }

    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_registered = $result->num_rows > 0;

    echo json_encode(['success' => true, 'is_registered' => $is_registered]);
}

function register() {
    file_put_contents('register.log', 'register function called' . PHP_EOL, FILE_APPEND);
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    file_put_contents('register.log', "email: $email, password: $password" . PHP_EOL, FILE_APPEND);

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        return;
    }

    $conn = getDbConnection();
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $password_hash);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User registered successfully']);
    } else {
        file_put_contents('register.log', "error: " . $stmt->error . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
}

function login() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        return;
    }

    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $conn->prepare("INSERT INTO tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user['id'], $token, $expires_at);
        $stmt->execute();

        echo json_encode(['success' => true, 'token' => $token, 'userId' => $user['id']]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

function checkAuth() {
    $user_id = validateToken();
    echo json_encode(['success' => true, 'is_authenticated' => true, 'userId' => $user_id]);
}
