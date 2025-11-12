<?php
// File: backend/migrations/add_line_number_column.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_operations.php';

echo "开始添加 line_number 列到 parsed_bets 表...\n";

try {
    $pdo = get_db_connection();
    
    // 检查列是否已存在
    $check_stmt = $pdo->query("SHOW COLUMNS FROM parsed_bets LIKE 'line_number'");
    if ($check_stmt->rowCount() > 0) {
        echo "line_number 列已存在，跳过迁移。\n";
        exit(0);
    }
    
    // 添加 line_number 列
    $sql = "ALTER TABLE parsed_bets ADD COLUMN line_number INT NULL AFTER ai_model_used";
    $pdo->exec($sql);
    
    echo "✅ 成功添加 line_number 列到 parsed_bets 表\n";
    
} catch (PDOException $e) {
    echo "❌ 迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
?>