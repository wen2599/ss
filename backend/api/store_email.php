<?php
// --- API: 存储来自 Cloudflare Worker 的邮件 ---

header('Content-Type: application/json');

// --- 引入配置和函数 ---
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'your_database_user';
$db_pass = getenv('DB_PASS') ?: 'your_database_password';
$db_name = getenv('DB_NAME') ?: 'your_database_name';
$api_secret_key = getenv('API_SECRET_KEY') ?: 'a_very_strong_and_secret_key'; // 与 Worker 共享的密钥

function send_json_response($success, $message, $code = 200) {
    http_response_code($success ? $code : 500);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// --- 主逻辑 ---

// 1. 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, '无效的请求方法，请使用 POST。', 405);
}

// 2. 验证来自 Cloudflare Worker 的共享密钥
$auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if ($auth_header !== 'Bearer ' . $api_secret_key) {
    send_json_response(false, '未授权的请求。', 401);
}

// 3. 获取并解析 JSON 输入
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_response(false, '无效的 JSON 数据。', 400);
}

// 4. 从解析的数据中提取邮件信息
$sender = $input['from'] ?? 'unknown';
$recipient = $input['to'] ?? 'unknown';
$subject = $input['subject'] ?? '';
$body_plain = $input['body_plain'] ?? '';
$body_html = $input['body_html'] ?? '';
$raw_email = $input['raw'] ?? '';

// 5. 连接数据库
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    send_json_response(false, '数据库服务暂时不可用。', 503);
}
$conn->set_charset('utf8mb4');

// 6. 准备 SQL 并插入数据
try {
    $stmt = $conn->prepare(
        "INSERT INTO emails (sender, recipient, subject, body_plain, body_html, raw_email) VALUES (?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new Exception("SQL 预处理失败: " . $conn->error);
    }

    $stmt->bind_param("ssssss", $sender, $recipient, $subject, $body_plain, $body_html, $raw_email);

    if ($stmt->execute()) {
        send_json_response(true, '邮件存储成功。', 201); // 201 Created
    } else {
        throw new Exception("邮件数据插入失败: " . $stmt->error);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    send_json_response(false, '存储邮件时发生内部错误。', 500);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}

?>
