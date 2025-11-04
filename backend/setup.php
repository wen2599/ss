<?php
// setup.php - Rebuilds the database table

// 在脚本开始时显示所有错误，方便调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 引入数据库连接函数
require_once 'db.php';

echo "Script started. Attempting to connect to the database...\n<br>";

// 获取数据库连接
$conn = get_db_connection();

// 检查连接是否成功
if (!$conn) {
    die("FATAL ERROR: Could not connect to the database. Please check your .env file and db.php script.\n<br>");
}

echo "Database connection successful!\n<br>";

// 定义表名
$tableName = "lottery_results";

// --- 1. 删除旧表 (如果存在) ---
$dropSql = "DROP TABLE IF EXISTS `{$tableName}`;";
echo "Executing: {$dropSql}\n<br>";

if ($conn->query($dropSql) === TRUE) {
    echo "SUCCESS: Old table '{$tableName}' dropped (if it existed).\n<br>";
} else {
    // 即使删除失败也继续，因为可能表本来就不存在
    echo "WARNING: Could not drop old table. Error: " . $conn->error . ". Continuing anyway...\n<br>";
}

// --- 2. 创建新表 ---
// 这是为您的项目量身定制的、最健壮的表结构
// - 使用 utf8mb4_unicode_ci 排序规则，更好地支持包括 emoji 在内的所有字符
// - 为 lottery_type 和 issue_number 添加了索引，以提高未来查询性能
$createSql = "
CREATE TABLE `{$tableName}` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lottery_type` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_number` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `winning_numbers` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_lottery_type` (`lottery_type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

echo "Executing CREATE TABLE statement...\n<br>";

if ($conn->query($createSql) === TRUE) {
    echo "SUCCESS: Table '{$tableName}' was created successfully with the new structure.\n<br>";
} else {
    die("FATAL ERROR: Failed to create new table. Error: " . $conn->error . "\n<br>");
}

// 关闭连接
$conn->close();

echo "Script finished.\n<br>";
?>