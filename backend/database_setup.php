<?php
// --- 数据库一键安装脚本 ---

// Load the environment variables
require_once __DIR__ . '/utils/config_loader.php';

// 加载 .env 文件中的配置
// 注意：在共享主机环境，您可能需要手动填写下面的变量
// 或者使用更复杂的库来解析 .env 文件。
// 为简单起见，我们这里直接定义，或假设您已设置为环境变量。

$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER') ?: 'your_database_user';
$db_pass = getenv('DB_PASS') ?: 'your_database_password';
$db_name = getenv('DB_NAME') ?: 'your_database_name';

// 1. 连接到 MySQL 服务器
$conn = new mysqli($db_host, $db_user, $db_pass);

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error . "\n");
}

// 2. 创建数据库 (如果不存在)
try {
    $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "数据库 '$db_name' 检查/创建完毕。\n";
    // 连接到指定数据库
    $conn->select_db($db_name);
} catch (mysqli_sql_exception $e) {
    die("创建数据库失败: " . $e->getMessage() . "\n");
}

// --- SQL 创建数据表语句 ---

// 3. 创建 users 表 (用于邮箱注册)
$sql_users = "CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL, -- 存储哈希后的密码
    `registration_ip` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// 4. 创建 lottery_numbers 表 (存储开奖号码)
$sql_lottery = "CREATE TABLE IF NOT EXISTS `lottery_numbers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `number` VARCHAR(255) NOT NULL, -- 开奖号码
    `source` VARCHAR(50) DEFAULT 'telegram', -- 数据来源 (例如: telegram)
    `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// 5. 创建 emails 表 (存储从 Cloudflare Worker 收到的邮件)
$sql_emails = "CREATE TABLE IF NOT EXISTS `emails` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender` VARCHAR(255) NOT NULL,
    `recipient` VARCHAR(255) NOT NULL,
    `subject` TEXT,
    `body_plain` TEXT, -- 纯文本邮件内容
    `body_html` LONGTEXT, -- HTML 邮件内容
    `raw_email` LONGTEXT, -- 原始邮件数据
    `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";


// --- 执行 SQL --- 

function execute_query($connection, $sql, $table_name) {
    try {
        if ($connection->query($sql) === TRUE) {
            echo "数据表 '$table_name' 检查/创建成功。\n";
        } else {
            // 虽然 query 失败会抛出异常，但为了保险起见
            throw new mysqli_sql_exception("查询失败");
        }
    } catch (mysqli_sql_exception $e) {
        die("创建数据表 '$table_name' 失败: " . $e->getMessage() . "\n");
    }
}

execute_query($conn, $sql_users, 'users');
execute_query($conn, $sql_lottery, 'lottery_numbers');
execute_query($conn, $sql_emails, 'emails');


$conn->close();

echo "\n数据库初始化完成！\n";

?>
