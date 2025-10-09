<?php
// backend/setup_database.php

require_once __DIR__ . '/bootstrap.php';

// SQL to create the lottery_results table
$sql = <<<'EOT'
CREATE TABLE IF NOT EXISTS lottery_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lottery_type VARCHAR(255) NOT NULL,
    numbers VARCHAR(255) NOT NULL,
    drawn_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
EOT;

try {
    $pdo = get_db_connection();
    $pdo->exec($sql);
    echo "Table 'lottery_results' created successfully." . PHP_EOL;
} catch (\PDOException $e) {
    die("Could not create table: " . $e->getMessage());
}

?>