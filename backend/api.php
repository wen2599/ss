<?php
/**
 * 文件名: api.php
 * 路径: backend/ (项目根目录)
 * 版本: Final with User Authentication
 */
// 错误报告（在开发时开启）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. CORS 头
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true"); // 允许凭证
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(204); exit; }

// 2. 引入所有核心文件
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helpers.php';
// ai_client.php 和 settlement_engine.php 会在需要时被调用，这里可以不引入

// 3. API 路由
$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    // === 公开路由 ===
    case 'get_latest_lottery':
        handle_get_latest_lottery();
        break;
    case 'register':
        handle_register($data);
        break;
    case 'login':
        handle_login($data);
        break;

    // === 受保护的路由 (需要JWT Token) ===
    case 'get_user_emails':
        handle_get_user_emails();
        break;

    default:
        json_response(['message' => 'Invalid API action'], 404);
        break;
}

// ===================================================
// 4. 处理器函数
// ===================================================

function handle_get_latest_lottery() {
    try {
        $db = get_db_connection();
        $stmt = $db->query("SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            json_response($result, 200);
        } else {
            json_response(['message' => 'No lottery results found.'], 404);
        }
    } catch (PDOException $e) {
        error_log("API Error (get_latest_lottery): " . $e->getMessage());
        json_response(['message' => 'A database error occurred.'], 500);
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
    } catch (PDOException $e) {
        error_log("API Error (register): " . $e->getMessage());
        json_response(['message' => 'Database error during registration.'], 500);
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
            unset($user['password_hash']); // 不要在响应中返回密码哈希
            json_response(['token' => $token, 'user' => $user], 200);
        } else {
            json_response(['message' => 'Invalid credentials.'], 401);
        }
    } catch (PDOException $e) {
        error_log("API Error (login): " . $e->getMessage());
        json_response(['message' => 'Database error during login.'], 500);
    }
}

// --- 这是一个受保护的示例函数 ---
function handle_get_user_emails() {
    $user = get_auth_user(); // get_auth_user() 会检查并解码JWT
    if (!$user) {
        json_response(['message' => 'Unauthorized'], 401);
    }
    
    // 既然已经验证了用户身份，我们可以安全地查询他的数据
    // 在这里添加查询该用户邮件的逻辑
    // ...
    
    // 暂时返回一个模拟响应
    json_response([
        ['id' => 1, 'subject' => '来自 ' . $user->email . ' 的第一封邮件'],
        ['id' => 2, 'subject' => '来自 ' . $user->email . ' 的第二封邮件']
    ]);
}
?>