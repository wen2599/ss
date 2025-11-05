<?php
/**
 * ==================================================================
 *  独立查询脚本 (get_results.php)
 * ==================================================================
 *  职责: 从数据库中查询最新的开奖号码，并以JSON格式返回。
 *  特点: 完全自包含，不依赖项目的其他部分，直接响应HTTP GET请求。
 *  调用方: Telegram Bot, 前端页面等。
 *  URL: https://yourdomain.com/get_results.php?limit=5
 * ==================================================================
 */

// --- 1. 初始化与错误处理 ---

// 将所有错误记录到日志文件，而不是显示给用户，防止破坏JSON输出
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/results-error.log'); // 在同目录下创建 results-error.log
error_reporting(E_ALL);

// 设置响应头，告知客户端返回的是JSON数据
header('Content-Type: application/json; charset=utf-8');


// --- 2. 加载环境变量 ---

$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        // 使用 '=' 作为分隔符，最多分成两部分
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            // 确保name和value都被trim，并且去除value可能带有的引号
            putenv(sprintf('%s=%s', trim($name), trim(trim($value), "\"'")));
        }
    }
}

// --- 3. 数据库连接 ---

// 从环境变量中获取数据库凭证
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$port = getenv('DB_PORT') ?: '3306';
$charset = 'utf8mb4';

// 检查是否所有必要的数据库配置都已加载
if (!$host || !$db || !$user || !$pass) {
    error_log("DB Connection Failed: Missing one or more database environment variables.");
    http_response_code(500); // 500 Internal Server Error
    echo json_encode(['error' => 'Server database configuration is incomplete.']);
    exit;
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // 尝试建立数据库连接
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // 如果连接失败，记录详细错误到日志，并返回一个通用的服务器错误信息
    error_log("DB Connection Failed in get_results.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not connect to the database.']);
    exit;
}


// --- 4. 执行查询逻辑 ---

// 从URL参数获取 limit，并进行安全验证
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
if ($limit <= 0 || $limit > 100) { // 防止无效或过大的limit值
    $limit = 5; 
}

try {
    // 准备SQL查询语句，使用预处理语句防止SQL注入
    $stmt = $pdo->prepare(
        "SELECT issue_number, numbers, draw_date FROM winning_numbers ORDER BY draw_date DESC, issue_number DESC LIMIT ?"
    );
    // 绑定 limit 参数
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    // 执行查询
    $stmt->execute();
    // 获取所有结果
    $numbers = $stmt->fetchAll();

    // 成功查询，返回200 OK状态码和JSON数据
    http_response_code(200);
    echo json_encode($numbers);

} catch (\PDOException $e) {
    // 如果SQL查询失败，记录详细错误，并返回服务器错误信息
    error_log("SQL Query Failed in get_results.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database query error occurred.']);
    exit;
}
?>