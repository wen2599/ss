<?php
// --- API: 获取所有存储的邮件 ---

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
$api_secret_key = getenv('API_SECRET_KEY') ?: 'a_very_strong_and_secret_key';

function send_json_response($success, $message, $data = null, $code = 200) {
    http_response_code($success ? $code : 500);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// 1. (可选但推荐) API 密钥验证
$auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
if (!$auth_header || $auth_header !== 'Bearer ' . $api_secret_key) {
    // send_json_response(false, '未授权的访问。', null, 401);
}

// 2. 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(false, '无效的请求方法，请使用 GET。', null, 405);
}

// 3. 连接数据库
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    send_json_response(false, '数据库服务暂时不可用。');
}
$conn->set_charset('utf8mb4');

// 4. 查询数据
try {
    // 选择需要的字段，按时间倒序排列
    // 为了性能，不总是需要选择 raw_email 字段
    $query = "SELECT id, sender, recipient, subject, body_plain, received_at FROM emails ORDER BY received_at DESC LIMIT 100"; // 限制返回数量
    $result = $conn->query($query);

    if ($result === false) {
        throw new Exception('查询失败: ' . $conn->error);
    }

    $emails = [];
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row;
    }

    send_json_response(true, '成功获取邮件列表。', $emails);

} catch (Exception $e) {
    error_log($e->getMessage());
    send_json_response(false, '获取邮件时发生错误。');
} finally {
    if(isset($result) && $result) $result->free();
    $conn->close();
}

?>
