<?php
/**
 * 文件名: install.php
 * 路径: backend/install.php
 */
if (php_sapi_name() !== 'cli') { 
    die("This script can only be run from the command line."); 
}

require_once __DIR__ . '/core/db.php'; 
$db = get_db_connection();
echo "Starting database installation...\n";
$sql = "CREATE TABLE IF NOT EXISTS `lottery_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY, `issue_number` VARCHAR(50) NOT NULL UNIQUE,
    `winning_numbers` VARCHAR(255) NOT NULL, `special_number` VARCHAR(10) NOT NULL,
    `draw_date` DATE NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
try {
    $db->exec($sql);
    echo "Successfully created 'lottery_results' table.\n";
} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage() . "\n");
}
echo "Installation script finished successfully.\n";