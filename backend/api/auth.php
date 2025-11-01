<?php
// backend/api/auth.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db_connection.php';

session_start();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$conn = get_db_connection(); // Get connection outside of specific action blocks
if (!$conn) {
    http_response_code(500);
    error_log("Auth API: Database connection failed."); // Log database connection failure
    echo json_encode(['success' => false, 'message' => '数据库连接失败。']);
    exit;
}

if ($method === 'POST' && $action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '邮箱和密码不能为空。']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的邮箱格式。']);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    if (!$stmt) {
        http_response_code(500);
        error_log("Auth API: Register prepare failed: " . $conn->error); // Log prepare error
        echo json_encode(['success' => false, 'message' => '准备注册查询失败：' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ss", $email, $hashed_password);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '注册成功。']);
    } else {
        if ($conn->errno === 1062) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => '该邮箱已被注册。']);
        } else {
            http_response_code(500);
            error_log("Auth API: Register execute failed: " . $conn->error); // Log execute error
            echo json_encode(['success' => false, 'message' => '注册失败，请稍后再试。']);
        }
    }
    $stmt->close();
} elseif ($method === 'POST' && $action === 'login') {
    // --- DEBUGGING: Log incoming request data and processing steps ---
    $debug_log_file = __DIR__ . '/auth_detailed_debug.log';
    $log_entry = "\n====================================\n";
    $log_entry .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $log_entry .= "Request Method: " . $method . "\n";
    $log_entry .= "Action: " . $action . "\n";
    $log_entry .= "Headers: " . print_r(getallheaders(), true) . "\n";
    $log_entry .= "GET Data: " . print_r($_GET, true) . "\n";
    $log_entry .= "POST Data: " . print_r($_POST, true) . "\n";

    $raw_input = file_get_contents('php://input');
    $log_entry .= "Raw Input (php://input): " . ($raw_input ? $raw_input : '[EMPTY]') . "\n";

    $data = json_decode($raw_input, true);
    $log_entry .= "JSON Decoded Data: " . print_r($data, true) . "\n";

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    $log_entry .= "Parsed Email: " . ($email ?? '[NULL]') . "\n";
    $log_entry .= "Parsed Password: " . ($password ? '[RECEIVED]' : '[NULL]') . "\n"; // Don't log actual password

    if (empty($email) || empty($password)) {
        $log_entry .= "Validation Failed: Email or Password is empty.\n";
        file_put_contents($debug_log_file, $log_entry, FILE_APPEND);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '登录失败，请检查您的凭据。']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
    if (!$stmt) {
        $log_entry .= "Prepare SQL Failed: " . $conn->error . "\n";
        file_put_contents($debug_log_file, $log_entry, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '准备登录查询失败：' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $log_entry .= "User found in DB: " . print_r($user, true) . "\n";

    if ($user && password_verify($password, $user['password'])) {
        $log_entry .= "Password Verified: TRUE\n";
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));

        $delete_stmt = $conn->prepare("DELETE FROM tokens WHERE user_id = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("i", $user['id']);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        
        $insert_stmt = $conn->prepare("INSERT INTO tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        if ($insert_stmt) {
            $insert_stmt->bind_param("iss", $user['id'], $token, $expires_at);
            if ($insert_stmt->execute()) {
                $log_entry .= "Token Inserted: TRUE\n";
                file_put_contents($debug_log_file, $log_entry, FILE_APPEND);
                echo json_encode([
                    'success' => true,
                    'message' => '登录成功。',
                    'user' => ['email' => $user['email']],
                    'token' => $token
                ]);
            } else {
                $log_entry .= "Token Insert Failed: " . $conn->error . "\n";
                file_put_contents($debug_log_file, $log_entry, FILE_APPEND);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => '登录失败：无法生成认证令牌。']);
            }
            $insert_stmt->close();
        } else {
            $log_entry .= "Token Prepare Failed: " . $conn->error . "\n";
            file_put_contents($debug_log_file, $log_entry, FILE_APPEND);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '登录失败：准备令牌插入失败：' . $conn->error]);
        }

    } else {
        $log_entry .= "Password Verified: FALSE or User Not Found.\n";
        file_put_contents($debug_log_file, $log_entry, FILE_APPEND);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '登录失败，请检查您的凭据。']);
    }
} elseif ($method === 'POST' && $action === 'logout') {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s((.*)\.(.*)\.(.*))/', $auth_header, $matches)) {
        $token = $matches[1];
        $delete_stmt = $conn->prepare("DELETE FROM tokens WHERE token = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("s", $token);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
    }

    session_destroy();
    echo json_encode(['success' => true, 'message' => '登出成功。']);
} elseif ($method === 'GET' && $action === 'check_session') {
    $is_logged_in = false;
    $user_email = null;

    if (isset($_SESSION['user_id'])) {
        $is_logged_in = true;
        $user_email = $_SESSION['user_email'];
    } else {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? '';
        if (preg_match('/Bearer\s((.*)\.(.*)\.(.*))/', $auth_header, $matches)) {
            $token_string = $matches[1];

            $stmt = $conn->prepare("SELECT u.id, u.email FROM tokens t JOIN users u ON t.user_id = u.id WHERE t.token = ? AND t.expires_at > NOW()");
            if ($stmt) {
                $stmt->bind_param("s", $token_string);
                $stmt->execute();
                $result = $stmt->get_result();
                $token_user = $result->fetch_assoc();
                $stmt->close();

                if ($token_user) {
                    $is_logged_in = true;
                    $user_email = $token_user['email'];
                    $_SESSION['user_id'] = $token_user['id'];
                    $_SESSION['user_email'] = $token_user['email'];
                }
            }
        }
    }

    echo json_encode(['success' => true, 'loggedIn' => $is_logged_in, 'user' => ['email' => $user_email]]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '无效的请求。']);
}

$conn->close();
