<?php
/**
 * 文件名: api.php
 * 路径: 项目根目录
 * 描述: 前端所有 API 请求的单一入口。
 */
ini_set('display_errors', 0); // 在生产环境中关闭错误显示
error_reporting(E_ALL);

// 1. CORS 头
header("Access-Control-Allow-Origin: " . ($_ENV['FRONTEND_URL'] ?? '*'));
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(204); exit; }

// 2. 引入核心文件
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/ai_client.php';
require_once __DIR__ . '/core/settlement_engine.php';

// 3. API 路由
$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    // 公开访问
    case 'get_latest_lottery':
        handle_get_latest_lottery();
        break;
    // 用户认证
    case 'register':
        handle_register($data);
        break;
    case 'login':
        handle_login($data);
        break;
    // 需要登录认证的接口
    case 'get_emails':
        handle_get_emails();
        break;
    case 'get_email_details':
        handle_get_email_details($_GET['id'] ?? null);
        break;
    // ... 未来添加更多需要认证的 case ...
    default:
        json_response(['message' => 'Invalid API action'], 404);
        break;
}

// 4. 处理器函数
function handle_get_latest_lottery() {
    try {
        $db = get_db_connection();
        $stmt = $db->query("SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) { json_response($result); } 
        else { json_response(['message' => 'No lottery results found.'], 404); }
    } catch (Exception $e) {
        error_log("API Error (get_latest_lottery): " . $e->getMessage());
        json_response(['message' => 'Server error'], 500);
    }
}

function handle_register($data) {
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    if (empty($email) || empty($password)) { json_response(['message' => 'Email and password are required.'], 400); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { json_response(['message' => 'Invalid email format.'], 400); }
    if (strlen($password) < 6) { json_response(['message' => 'Password must be at least 6 characters.'], 400); }

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) { json_response(['message' => 'Email already exists.'], 409); }
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
        $stmt->execute([$email, $password_hash]);
        json_response(['message' => 'User registered successfully.'], 201);
    } catch (Exception $e) {
        error_log("API Error (register): " . $e->getMessage());
        json_response(['message' => 'Server error'], 500);
    }
}

function handle_login($data) {
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    if (empty($email) || empty($password)) { json_response(['message' => 'Email and password are required.'], 400); }

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $token = generate_jwt($user['id'], $user['email']);
            unset($user['password_hash']);
            json_response(['token' => $token, 'user' => $user]);
        } else {
            json_response(['message' => 'Invalid credentials.'], 401);
        }
    } catch (Exception $e) {
        error_log("API Error (login): " . $e->getMessage());
        json_response(['message' => 'Server error'], 500);
    }
}

function handle_get_emails() {
    $user = get_auth_user();
    if (!$user) { json_response(['message' => 'Unauthorized'], 401); }
    
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT id, subject, from_address, status, created_at FROM received_emails WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user->user_id]);
        json_response($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        error_log("API Error (get_emails): " . $e->getMessage());
        json_response(['message' => 'Server error'], 500);
    }
}

function handle_get_email_details($email_id) {
    $user = get_auth_user();
    if (!$user) { json_response(['message' => 'Unauthorized'], 401); }
    if (!$email_id) { json_response(['message' => 'Email ID is required'], 400); }

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT * FROM received_emails WHERE id = ? AND user_id = ?");
        $stmt->execute([$email_id, $user->user_id]);
        $email = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($email) {
            $email['structured_data'] = $email['structured_data'] ? json_decode($email['structured_data'], true) : [];
            $email['settlement_result'] = $email['settlement_result'] ? json_decode($email['settlement_result'], true) : null;
            json_response($email);
        } else {
            json_response(['message' => 'Email not found.'], 404);
        }
    } catch (Exception $e) {
        error_log("API Error (get_email_details): " . $e->getMessage());
        json_response(['message' => 'Server error'], 500);
    }
}