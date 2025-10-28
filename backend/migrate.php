<?php

declare(strict_types=1);

// backend/migrate.php
// 统一的数据库迁移管理工具

require_once __DIR__ . '/bootstrap.php';

$db_connection = $GLOBALS['db_connection'] ?? null;

if (!$db_connection) {
    die("数据库连接失败，请检查bootstrap.php\n");
}

echo "\n\n--- 数据库迁移工具启动 ---\n";

// 创建或确保 migrations 表存在
$create_migrations_table_sql = <<<SQL
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration_name` VARCHAR(255) NOT NULL UNIQUE,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if ($db_connection->query($create_migrations_table_sql) === FALSE) {
    die("创建 `migrations` 表失败: " . $db_connection->error . "\n");
}

// 获取已执行的迁移列表
$executed_migrations = [];
$result = $db_connection->query("SELECT migration_name FROM migrations");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $executed_migrations[] = $row['migration_name'];
    }
}

$migrations_dir = __DIR__ . '/migrations';

// 确保 migrations 目录存在
if (!is_dir($migrations_dir)) {
    mkdir($migrations_dir, 0755, true);
    echo "[提示] 创建了 `migrations` 目录。\n";
}

$migration_files = [];
foreach (scandir($migrations_dir) as $file) {
    if (preg_match('/^\\d{3}_.*\\.php$/', $file)) {
        $migration_files[] = $file;
    }
}

sort($migration_files); // 确保按顺序执行

$new_migrations_found = false;
foreach ($migration_files as $file) {
    $migration_name = basename($file, '.php');
    if (!in_array($migration_name, $executed_migrations)) {
        $new_migrations_found = true;
        echo "\n[执行迁移] {$migration_name}...\n";
        try {
            require_once $migrations_dir . '/' . $file;

            // 假设每个迁移文件都定义了一个名为 `run_migration` 的函数
            // 如果没有，或者迁移失败，我们会在这里捕获
            if (function_exists('run_migration')) {
                run_migration($db_connection);
            } else {
                echo "[错误] 迁移文件 {$migration_name}.php 未定义 `run_migration` 函数。\n";
                continue; // 跳过此迁移，继续下一个
            }

            $stmt = $db_connection->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
            $stmt->bind_param("s", $migration_name);
            $stmt->execute();
            $stmt->close();
            echo "[成功] 迁移 {$migration_name} 已执行并记录。\n";
        } catch (Exception $e) {
            echo "[失败] 迁移 {$migration_name} 执行失败: " . $e->getMessage() . "\n";
            // 停止执行后续迁移，并考虑回滚（在此简化为直接退出）
            $db_connection->rollback();
            $db_connection->close();
            exit(1);
        }
    } else {
        echo "[跳过] 迁移 {$migration_name} 已执行。\n";
    }
}

if (!$new_migrations_found) {
    echo "\n所有迁移均已执行。数据库已是最新状态。\n";
}

echo "\n--- 数据库迁移工具结束 ---\n\n";

$db_connection->close();
