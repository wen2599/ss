<?php
require_once __DIR__ . '/bootstrap.php';

// Function to get the database connection
function get_db_connection() {
    $host = DB_HOST;
    $db   = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}

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