<?php
// backend/api/db_init.php
require_once 'config.php';

try {
    // Establish connection to MySQL
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // SQL to create draws table for MySQL
    $sql_draws = "CREATE TABLE IF NOT EXISTS draws (
        id INT PRIMARY KEY AUTO_INCREMENT,
        period VARCHAR(255) NOT NULL UNIQUE,
        winning_numbers VARCHAR(255) NOT NULL,
        draw_time DATETIME NOT NULL
    ) ENGINE=InnoDB;";
    $pdo->exec($sql_draws);

    // Check if there is any data in draws table
    $stmt = $pdo->query("SELECT COUNT(*) FROM draws");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Insert some dummy data if table is empty
        $pdo->exec("INSERT INTO draws (period, winning_numbers, draw_time) VALUES
            ('2024001', '01,02,03,04,05,06', '2024-01-01 21:30:00')
        ");
    }

    // SQL to create bets table for MySQL
    $sql_bets = "CREATE TABLE IF NOT EXISTS bets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        numbers VARCHAR(255) NOT NULL,
        period VARCHAR(255) NOT NULL,
        bet_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (period) REFERENCES draws (period)
    ) ENGINE=InnoDB;";
    $pdo->exec($sql_bets);

    echo "Database initialized successfully. Tables 'draws' and 'bets' are ready for MySQL.";

} catch (PDOException $e) {
    // It's better not to expose detailed error messages in production
    // For debugging, you can uncomment the line below
    // die("Database initialization failed: " . $e->getMessage());
    http_response_code(500);
    die("Database initialization failed. Please check the server logs.");
}
?>
