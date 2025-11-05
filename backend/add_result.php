<?php
// 启用错误日志
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/add-result-error.log');
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- 独立加载 .env ---
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(sprintf('%s=%s', trim($name), trim(trim($value), "\"'")));
    }
}

// --- 1. 安全验证 ---
$secret = getenv('INTERNAL_API_SECRET');
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$isAuthorized = false;

if ($secret && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    if (hash_equals($secret, $matches[1])) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// --- 2. 获取输入数据 ---
$input = json_decode(file_get_contents('php://input'), true);
$issue = $input['issue_number'] ?? null;
$numbers = $input['numbers'] ?? null;
$date = $input['draw_date'] ?? date('Y-m-d');

if (!$issue || !$numbers) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing issue_number or numbers']);
    exit;
}

// --- 3. 数据库操作 ---
$host = getenv('DB_HOST'); $db = getenv('DB_NAME'); $user = getenv('DB_USER');
$pass = getenv('DB_PASS'); $port = getenv('DB_PORT') ?: '3306';
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $stmt = $pdo->prepare("INSERT INTO winning_numbers (issue_number, numbers, draw_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numbers=VALUES(numbers), draw_date=VALUES(draw_date)");
    
    if ($stmt->execute([$issue, $numbers, $date])) {
        http_response_code(201);
        echo json_encode(['message' => 'Winning number added successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute statement.']);
    }
} catch (\PDOException $e) {
    error_log("DB Error in add_result.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database operation failed.']);
    exit;
}
?>