<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // 创建彩票结果表
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS lottery_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lottery_number VARCHAR(50) NOT NULL,
        lottery_type VARCHAR(50) NOT NULL,
        draw_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_draw (lottery_type, draw_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createTableSQL);
    echo "Table 'lottery_results' created successfully!\n";
    
    // 插入示例数据（可选）
    $sampleDataSQL = "
    INSERT IGNORE INTO lottery_results (lottery_number, lottery_type, draw_date) VALUES
    ('01 05 12 23 34 45+08', '双色球', '2024-01-15'),
    ('03 08 15 22 29 36+11', '双色球', '2024-01-12'),
    ('02 07 14 21 28 35+09', '大乐透', '2024-01-10');
    ";
    
    $pdo->exec($sampleDataSQL);
    echo "Sample data inserted successfully!\n";
    
    echo "Database setup completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>