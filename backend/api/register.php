<?php
// --- API: 用户邮箱注册 ---

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org'); // 锁定前端域名
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- 引入配置和函数 ---
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'your_database_user';
$db_pass = getenv('DB_PASS') ?: 'your_database_password';
$db_name = getenv('DB_NAME') ?: 'your_database_name';

function send_json_response($success, $message, $data = null, $code = 200) {
    http_response_code($success ? $code : ($code === 200 ? 400 : $code));
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// --- 主逻辑 ---

// 1. 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, '无效的请求方法，请使用 POST。', null, 405);
}

// 2. 获取并解析 JSON 输入
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : null;
$password = isset($input['password']) ? $input['password'] : null;

// 3. 验证输入数据
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(false, '邮箱格式不正确。');
}
if (empty($password) || strlen($password) < 6) { // 简单的密码长度验证
    send_json_response(false, '密码不能为空且长度至少为6位。');
}

// 4. 连接数据库
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    send_json_response(false, '注册服务暂时不可用。', null, 503);
}
$conn->set_charset('utf8mb4');

// 5. 检查邮箱是否已被注册
try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        send_json_response(false, '此邮箱已被注册。', null, 409); // 409 Conflict
    }
    $stmt->close();

    // 6. 哈希密码并插入新用户
    // 使用 PHP 内建的 password_hash()，安全可靠
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $registration_ip = $_SERVER['REMOTE_ADDR']; // 记录注册 IP

    $stmt = $conn->prepare("INSERT INTO users (email, password, registration_ip) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $hashed_password, $registration_ip);

    if ($stmt->execute()) {
        send_json_response(true, '恭喜！注册成功。', ['email' => $email], 201); // 201 Created
    } else {
        throw new Exception('创建用户时发生错误: ' . $stmt->error);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    send_json_response(false, '注册过程中发生内部错误。', null, 500);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}

?>
