<?php
// backend/api/auth.php
// Version 4.0: Unified and refactored authentication endpoint.

// This script is designed to be called by api_router.php, which handles:
// - Error reporting
// - Session start (though we are moving away from it)
// - JSON content type header
// - Database connection ($conn is globally available)

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Router configuration error: Database connection not available.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// --- ACTION: register ---
if ($method === 'POST' && $action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    if (!$stmt) {
        error_log("Auth API (Register): Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare registration query.']);
        exit;
    }
    $stmt->bind_param("ss", $email, $hashed_password);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Registration successful.']);
    } else {
        if ($conn->errno === 1062) { // Duplicate entry
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
        } else {
            error_log("Auth API (Register): Execute failed: " . $stmt->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Registration failed due to a server error.']);
        }
    }
    $stmt->close();

// --- ACTION: login ---
} elseif ($method === 'POST' && $action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
    if (!$stmt) {
        error_log("Auth API (Login): Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare login query.']);
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Generate a secure, random token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));

        // Clean up any old tokens for this user
        $delete_stmt = $conn->prepare("DELETE FROM tokens WHERE user_id = ?");
        $delete_stmt->bind_param("i", $user['id']);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Insert the new token
        $insert_stmt = $conn->prepare("INSERT INTO tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        if (!$insert_stmt) {
            error_log("Auth API (Login): Prepare token insert failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to prepare token generation.']);
            exit;
        }
        $insert_stmt->bind_param("iss", $user['id'], $token, $expires_at);

        if ($insert_stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Login successful.',
                'token' => $token,
                'user' => ['id' => $user['id'], 'email' => $user['email']]
            ]);
        } else {
            error_log("Auth API (Login): Execute token insert failed: " . $insert_stmt->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save authentication token.']);
        }
        $insert_stmt->close();

    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
    }

// --- ACTION: logout ---
} elseif ($method === 'POST' && $action === 'logout') {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s(.+)/', $auth_header, $matches)) {
        $token = $matches[1];
        $delete_stmt = $conn->prepare("DELETE FROM tokens WHERE token = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("s", $token);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
    }
    // Also destroy any lingering session data for good measure
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    echo json_encode(['success' => true, 'message' => 'Logout successful.']);

// --- ACTION: check_auth ---
} elseif ($method === 'GET' && $action === 'check_auth') {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';

    if (preg_match('/Bearer\s(.+)/', $auth_header, $matches)) {
        $token = $matches[1];

        $stmt = $conn->prepare("SELECT u.id, u.email FROM tokens t JOIN users u ON t.user_id = u.id WHERE t.token = ? AND t.expires_at > NOW()");
        if ($stmt) {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                echo json_encode(['success' => true, 'loggedIn' => true, 'user' => $user]);
                exit;
            }
        }
    }
    
    // If we reach here, the token was missing, invalid, or expired
    http_response_code(401);
    echo json_encode(['success' => false, 'loggedIn' => false, 'message' => 'Unauthorized.']);

} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Invalid auth action or method.']);
}
