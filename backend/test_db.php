<?php
// Enable full error reporting for debugging purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$env_path = __DIR__ . '/.env';
if (!file_exists($env_path)) {
    // Stop execution if the .env file is missing
    die("错误：在 " . __DIR__ . " 目录下找不到 .env 文件！");
}

// Read the .env file line by line
$lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    // Skip comments
    if (strpos(trim($line), '#') === 0) {
        continue;
    }
    // Parse name and value
    list($name, $value) = explode('=', $line, 2);
    // Remove quotes from the value, if they exist
    $_ENV[trim($name)] = trim(trim($value), '"\'');
}

// Get database credentials from the environment variables
$host = $_ENV['DB_HOST'] ?? null;
$dbname = $_ENV['DB_NAME'] ?? null;
$user = $_ENV['DB_USER'] ?? null;
$pass = $_ENV['DB_PASS'] ?? '';

// Check if all necessary credentials are provided
if (!$host || !$dbname || !$user) {
    die("错误：.env 文件中的数据库配置不完整 (DB_HOST, DB_NAME, DB_USER 必须全部设置)。");
}

// Display the credentials being used for verification
echo "正在尝试使用以下信息连接数据库...<br>";
echo "主机: " . htmlspecialchars($host) . "<br>";
echo "数据库名: " . htmlspecialchars($dbname) . "<br>";
echo "用户名: " . htmlspecialchars($user) . "<br>";
echo "密码: [出于安全考虑未显示]<br><br>";

// Attempt the database connection
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // If successful, print a green success message
    echo "<b><font color='green'>成功！数据库连接正常。</font></b>";
} catch (PDOException $e) {
    // If it fails, print a red error message with details
    echo "<b><font color='red'>失败！数据库连接错误。</font></b><br>";
    echo "错误信息: " . $e->getMessage();
}
?>