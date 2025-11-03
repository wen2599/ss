<?php
// --- API: 存储开奖号码 ---

header('Content-Type: application/json');

// 引入数据库连接配置
// 在实际部署中，创建一个 db_connect.php 文件会更整洁
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'your_database_user';
$db_pass = getenv('DB_PASS') ?: 'your_database_password';
$db_name = getenv('DB_NAME') ?: 'your_database_name';

// 响应助手函数
function send_json_response($success, $message, $data = null) {
    http_response_code($success ? 200 : 500);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// 1. 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, '无效的请求方法，请使用 POST。');
}

// 2. 获取并验证传入的号码
$number = isset($_POST['number']) ? trim($_POST['number']) : null;

if (empty($number)) {
    send_json_response(false, '开奖号码不能为空。');
}

// (可选) 进行更严格的号码格式验证
// 例如，如果号码必须是数字，可以使用 is_numeric()

// 3. 连接数据库
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    // 在生产环境中，不应暴露详细错误
    error_log("Database connection failed: " . $conn->connect_error);
    send_json_response(false, '数据库连接失败。');
}

$conn->set_charset('utf8mb4');

// 4. 准备 SQL 语句并插入数据 (使用预处理语句防止 SQL 注入)
try {
    $stmt = $conn->prepare("INSERT INTO lottery_numbers (number, source) VALUES (?, ?)");
    
    if ($stmt === false) {
        throw new Exception('SQL 预处理失败: ' . $conn->error);
    }
    
    $source = 'telegram'; // 数据来源
    $stmt->bind_param("ss", $number, $source);
    
    if ($stmt->execute()) {
        // 插入成功
        $new_id = $stmt->insert_id;
        send_json_response(true, '开奖号码存储成功。', ['id' => $new_id, 'number' => $number]);
    } else {
        throw new Exception('SQL 执行失败: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    send_json_response(false, '存储开奖号码时发生内部错误。');
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}

?>
