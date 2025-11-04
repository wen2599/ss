<?php
// backend/import_sql.php - Database Import Script

require_once 'config.php';

function importDatabase() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER, 
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // 创建开奖号码表
        $sql = "
        CREATE TABLE IF NOT EXISTS lottery_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            draw_time DATETIME NOT NULL,
            numbers VARCHAR(255) NOT NULL,
            channel_name VARCHAR(255),
            message_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE INDEX idx_draw_time ON lottery_results(draw_time);
        CREATE INDEX idx_channel ON lottery_results(channel_name);
        ";
        
        $pdo->exec($sql);
        echo "数据库表 'lottery_results' 创建成功或已存在！\n";
        
    } catch(PDOException $e) {
        die("数据库错误: " . $e->getMessage());
    }
}

importDatabase();
?>