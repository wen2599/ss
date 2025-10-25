<?php
// backend/migrate_add_lottery_type.php

// --- Bootstrap aplication ---
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "数据库迁移脚本开始...\n\n";

global $db_connection;

// 1. 检查 `lottery_type` 字段是否已存在
$check_column_query = "SHOW COLUMNS FROM `lottery_draws` LIKE 'lottery_type'";
$result = $db_connection->query($check_column_query);

if ($result && $result->num_rows > 0) {
    echo "[跳过] `lottery_type` 字段已经存在于 `lottery_draws` 表中。无需操作。\n";
} else {
    echo "[执行] `lottery_type` 字段不存在，正在添加...\n";
    // 2. 如果字段不存在，则添加它
    // 我们将它放在 `draw_period` 字段之后，以保持结构清晰
    $alter_table_query = "ALTER TABLE `lottery_draws` ADD `lottery_type` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `draw_period`";
    
    if ($db_connection->query($alter_table_query) === TRUE) {
        echo "[成功] `lottery_type` 字段已成功添加到 `lottery_draws` 表中。\n";
    } else {
        echo "[错误] 添加字段失败: " . $db_connection->error . "\n";
        $db_connection->close();
        exit; // 出错时停止执行
    }
}

// 3. 为 `lottery_type` 和 `draw_period` 创建复合唯一索引
// 这可以防止为同一种彩票的同一期号记录重复的数据
$index_name = 'unique_type_period';
$check_index_query = "SHOW INDEX FROM `lottery_draws` WHERE Key_name = '{$index_name}'";
$result = $db_connection->query($check_index_query);

if ($result && $result->num_rows > 0) {
    echo "[跳过] 复合唯一索引 `{$index_name}` 已经存在。\n";
} else {
    echo "[执行] 正在为 `lottery_type` 和 `draw_period` 创建复合唯一索引 `{$index_name}`...\n";
    // 首先移除可能存在的旧的、仅基于 draw_period 的唯一索引 (如果存在)
    // 注意: 这种检查和删除索引的语法在 MySQL 中是安全的
    $db_connection->query("ALTER TABLE `lottery_draws` DROP INDEX `draw_period`"); // 如果不存在，这句会无害地失败
    
    // 创建新的复合索引
    $create_index_query = "ALTER TABLE `lottery_draws` ADD UNIQUE `{$index_name}` (`lottery_type`, `draw_period`)";
    if ($db_connection->query($create_index_query) === TRUE) {
        echo "[成功] 复合唯一索引 `{$index_name}` 已成功创建。\n";
    } else {
        echo "[错误] 创建复合索引失败: " . $db_connection->error . "\n";
    }
}

$db_connection->close();

echo "\n数据库迁移脚本执行完毕。\n";

?>