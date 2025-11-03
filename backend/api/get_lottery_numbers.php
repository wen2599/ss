<?php
// --- API: 获取所有开奖号码 ---

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许所有来源的跨域请求 (为了简单起见)
// 在生产环境中，建议替换为您的前端域名: header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理 OPTIONS 预检请求 (CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content
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
    // 如果在 frontend/_worker.js 中处理请求头，这层验证可以确保只有您的前端能调用
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
    // 按接收时间倒序排列，最新的号码在最前面
    $result = $conn->query("SELECT id, number, received_at FROM lottery_numbers ORDER BY received_at DESC");

    if ($result === false) {
        throw new Exception('查询失败: ' . $conn->error);
    }

    $numbers = [];
    while ($row = $result->fetch_assoc()) {
        $numbers[] = $row;
    }

    send_json_response(true, '成功获取开奖号码列表。', $numbers);

} catch (Exception $e) {
    error_log($e->getMessage());
    send_json_response(false, '获取数据时发生错误。');
} finally {
    if(isset($result) && $result) $result->free();
    $conn->close();
}

?>
