<?php

declare(strict_types=1);

// backend/migrations/000_add_lottery_type.php

function run_migration(mysqli $db_connection): void
{
    echo "[任务] 正在处理迁移 000_add_lottery_type...\n";

    // 1. 检查 `lottery_type` 字段是否已存在
    $check_column_query = "SHOW COLUMNS FROM `lottery_draws` LIKE 'lottery_type'";
    $result = $db_connection->query($check_column_query);

    if ($result && $result->num_rows > 0) {
        echo "[跳过] `lottery_type` 字段已经存在于 `lottery_draws` 表中。\n";
    } else {
        echo "[执行] `lottery_type` 字段不存在，正在添加...\n";
        $alter_table_query = "ALTER TABLE `lottery_draws` ADD `lottery_type` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `draw_period`";
        
        if ($db_connection->query($alter_table_query) === TRUE) {
            echo "[成功] `lottery_type` 字段已成功添加到 `lottery_draws` 表中。\n";
        } else {
            throw new Exception("添加 `lottery_type` 字段失败: " . $db_connection->error);
        }
    }

    // 2. 为 `lottery_type` 和 `draw_period` 创建复合唯一索引
    $index_name = 'unique_type_period';
    $check_index_query = "SHOW INDEX FROM `lottery_draws` WHERE Key_name = '{$index_name}'";
    $result = $db_connection->query($check_index_query);

    if ($result && $result->num_rows > 0) {
        echo "[跳过] 复合唯一索引 `{$index_name}` 已经存在。\n";
    } else {
        echo "[执行] 正在为 `lottery_type` 和 `draw_period` 创建复合唯一索引 `{$index_name}`...\n";
        // 尝试移除可能存在的旧的、仅基于 draw_period 的唯一索引 (如果存在，无害)
        $db_connection->query("ALTER TABLE `lottery_draws` DROP INDEX `draw_period`");
        
        $create_index_query = "ALTER TABLE `lottery_draws` ADD UNIQUE `{$index_name}` (`lottery_type`, `draw_period`)";
        if ($db_connection->query($create_index_query) === TRUE) {
            echo "[成功] 复合唯一索引 `{$index_name}` 已成功创建。\n";
        } else {
            throw new Exception("创建复合索引失败: " . $db_connection->error);
        }
    }
    echo "[完成] 迁移 000_add_lottery_type 处理完毕。\n";
}
