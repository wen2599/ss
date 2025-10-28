<?php

declare(strict_types=1);

// backend/migrations/001_add_settlements_and_email_status.php

function run_migration(mysqli $db_connection): void
{
    echo "[任务] 正在处理迁移 001_add_settlements_and_email_status...\n";

    // --- 任务1: 创建 `settlements` 表 ---
    echo "[子任务 1/2] 正在检查并创建 `settlements` 表...\n";
    $table_name = 'settlements';
    $check_table_query = "SHOW TABLES LIKE '{$table_name}'";
    $result = $db_connection->query($check_table_query);

    if ($result && $result->num_rows > 0) {
        echo "[跳过] `{$table_name}` 表已经存在。\n";
    } else {
        echo "[执行] 正在创建 `{$table_name}` 表...\n";
        $create_table_sql = <<<SQL
        CREATE TABLE `settlements` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `draw_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
          `total_winnings` decimal(10,2) DEFAULT NULL,
          `settlement_data` json NOT NULL,
          `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_settlement',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_email_id` (`email_id`),
          KEY `user_id` (`user_id`),
          KEY `draw_period` (`draw_period`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        if ($db_connection->query($create_table_sql) === TRUE) {
            echo "[成功] `{$table_name}` 表已成功创建。\n";
        } else {
            throw new Exception("创建 `{$table_name}` 表失败: " . $db_connection->error);
        }
    }

    // --- 任务2: 为 `emails` 表添加 `is_processed` 字段 ---
    echo "[子任务 2/2] 正在检查并为 `emails` 表添加 `is_processed` 字段...\n";
    $column_name = 'is_processed';
    $check_column_query = "SHOW COLUMNS FROM `emails` LIKE '{$column_name}'";
    $result = $db_connection->query($check_column_query);

    if ($result && $result->num_rows > 0) {
        echo "[跳过] `{$column_name}` 字段已经存在于 `emails` 表中。\n";
    } else {
        echo "[执行] 正在添加 `{$column_name}` 字段...\n";
        $alter_table_sql = "ALTER TABLE `emails` ADD `is_processed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `body`";
        if ($db_connection->query($alter_table_sql) === TRUE) {
            echo "[成功] `{$column_name}` 字段已成功添加到 `emails` 表中。\n";
        } else {
            throw new Exception("添加 `{$column_name}` 字段失败: " . $db_connection->error);
        }
    }
    echo "[完成] 迁移 001_add_settlements_and_email_status 处理完毕。\n";
}
