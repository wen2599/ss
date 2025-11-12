<?php
// File: backend/migrations/create_odds_template_table.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_operations.php';

echo "开始创建赔率模板表...\n";

try {
    $pdo = get_db_connection();
    
    // 检查表是否存在
    $check_table = $pdo->query("SHOW TABLES LIKE 'user_odds_templates'");
    if ($check_table->rowCount() > 0) {
        echo "user_odds_templates 表已存在，检查列结构...\n";
        
        // 检查各列是否存在，不存在则添加
        $columns_to_add = [
            'special_code_odds' => 'DECIMAL(8,2) NULL',
            'flat_special_odds' => 'DECIMAL(8,2) NULL', 
            'serial_code_odds' => 'DECIMAL(8,2) NULL',
            'even_xiao_odds' => 'DECIMAL(8,2) NULL',
            'six_xiao_odds' => 'DECIMAL(8,2) NULL',
            'size_single_double_odds' => 'DECIMAL(8,2) NULL'
        ];
        
        foreach ($columns_to_add as $column => $type) {
            $check_column = $pdo->query("SHOW COLUMNS FROM user_odds_templates LIKE '{$column}'");
            if ($check_column->rowCount() === 0) {
                $sql = "ALTER TABLE user_odds_templates ADD COLUMN {$column} {$type}";
                $pdo->exec($sql);
                echo "✅ 成功添加 {$column} 列\n";
            } else {
                echo "{$column} 列已存在，跳过\n";
            }
        }
        
    } else {
        // 创建新表
        $sql = "
        CREATE TABLE IF NOT EXISTS `user_odds_templates` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT NOT NULL,
          `special_code_odds` DECIMAL(8,2) NULL,
          `flat_special_odds` DECIMAL(8,2) NULL,
          `serial_code_odds` DECIMAL(8,2) NULL,
          `even_xiao_odds` DECIMAL(8,2) NULL,
          `six_xiao_odds` DECIMAL(8,2) NULL,
          `size_single_double_odds` DECIMAL(8,2) NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          UNIQUE KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql);
        echo "✅ 成功创建 user_odds_templates 表\n";
    }
    
    echo "✅ 赔率模板表结构检查完成\n";
    
} catch (PDOException $e) {
    echo "❌ 迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
?>