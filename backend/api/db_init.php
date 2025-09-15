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
        period VARCHAR(255) NOT NULL,
        winning_numbers VARCHAR(255) NOT NULL,
        draw_time DATETIME NOT NULL,
        lottery_type VARCHAR(255) NOT NULL,
        UNIQUE KEY (period, lottery_type)
    ) ENGINE=InnoDB;";
    $pdo->exec($sql_draws);

    // Check if there is any data in draws table
    $stmt = $pdo->query("SELECT COUNT(*) FROM draws");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Insert some dummy data if table is empty
        $pdo->exec("INSERT INTO draws (period, winning_numbers, draw_time, lottery_type) VALUES
            ('2024001', '01,02,03,04,05,06', '2024-01-01 21:30:00', 'Xin Ao'),
            ('2024001', '07,08,09,10,11,12', '2024-01-01 21:30:00', 'Lao Ao'),
            ('2024001', '13,14,15,16,17,18', '2024-01-01 21:30:00', 'Gang Cai')
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

    // SQL to create telegram_messages table for MySQL
    $sql_telegram_messages = "CREATE TABLE IF NOT EXISTS telegram_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        message_id BIGINT NOT NULL UNIQUE,
        chat_id BIGINT NOT NULL,
        message_json JSON NOT NULL,
        received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";
    $pdo->exec($sql_telegram_messages);

    // SQL to create users table for MySQL
    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        phone VARCHAR(255) NULL UNIQUE,
        points INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";
    $pdo->exec($sql_users);

    // SQL to create tg_admins table
    $sql_tg_admins = "CREATE TABLE IF NOT EXISTS tg_admins (
        chat_id BIGINT PRIMARY KEY
    ) ENGINE=InnoDB;";
    $pdo->exec($sql_tg_admins);

    // SQL to create tg_admin_states table
    $sql_tg_admin_states = "CREATE TABLE IF NOT EXISTS tg_admin_states (
        chat_id BIGINT PRIMARY KEY,
        state VARCHAR(255) NULL,
        state_data TEXT NULL
    ) ENGINE=InnoDB;";
    $pdo->exec($sql_tg_admin_states);

    echo "Database initialized successfully. All tables are ready for MySQL.";

} catch (PDOException $e) {
    // It's better not to expose detailed error messages in production
    // For debugging, you can uncomment the line below
    // die("Database initialization failed: " . $e->getMessage());
    http_response_code(500);
    die("Database initialization failed. Please check the server logs.");
}
?>
