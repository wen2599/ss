<?php
// backend/database_setup.php

require_once __DIR__ . '/utils/config_loader.php';

// ---- 主逻辑开始 ----

// 从环境变量中获取配置
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

if (!$host || !$user || !$pass || !$db_name) {
    fwrite(STDERR, "错误: .env 文件中数据库配置不完整。请确保 DB_HOST, DB_USER, DB_PASS, DB_NAME 都已设置。\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    echo "正在使用主机 '{$host}' 连接数据库...\n";
    $conn = new mysqli($host, $user, $pass, $db_name);
    $conn->set_charset("utf8mb4");

    echo "\n[成功] 数据库连接成功！\n\n";

    // --- 创建 users 表 ---
    $sql_users = "CREATE TABLE IF NOT EXISTS `users` ( `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, `email` VARCHAR(191) NOT NULL, `password` VARCHAR(255) NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `email_unique` (`email`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql_users)) echo "数据表 'users' 创建成功或已存在。\n";

    // --- 创建 lottery_numbers 表 ---
    $sql_numbers = "CREATE TABLE IF NOT EXISTS `lottery_numbers` ( `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, `number` VARCHAR(255) NOT NULL, `issue_date` DATE NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `issue_date_unique` (`issue_date`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql_numbers)) echo "数据表 'lottery_numbers' 创建成功或已存在。\n";

    // --- 创建 tokens 表 ---
    $sql_tokens = "CREATE TABLE IF NOT EXISTS `tokens` ( `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, `user_id` INT(11) UNSIGNED NOT NULL, `token` VARCHAR(64) NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `token_unique` (`token`), FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql_tokens)) echo "数据表 'tokens' 创建成功或已存在。\n";

    echo "\n数据库初始化流程全部完成。\n";

} catch (mysqli_sql_exception $e) {
    fwrite(STDERR, "\n[失败] 数据库操作失败: " . $e->getMessage() . "\n");
    fwrite(STDERR, "错误码: " . $e->getCode() . "\n");
    if ($e->getCode() === 2002) {
        fwrite(STDERR, "提示: 无法连接到数据库主机 '{$host}'。请检查主机名是否正确以及服务器网络。\n");
    } elseif ($e->getCode() === 1045) {
        fwrite(STDERR, "提示: 访问被拒绝。这通常意味着用户名或密码错误。\n");
    }
    exit(1);
} finally {
    if (isset($conn)) $conn->close();
}

exit(0);
?>