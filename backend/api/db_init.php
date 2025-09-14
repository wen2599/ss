<?php
// backend/api/db_init.php
require_once 'config.php';

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create draws table
    $pdo->exec("CREATE TABLE IF NOT EXISTS draws (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        period TEXT NOT NULL UNIQUE,
        winning_numbers TEXT NOT NULL,
        draw_time DATETIME NOT NULL
    )");

    // Check if there is any data in draws table
    $stmt = $pdo->query("SELECT COUNT(*) FROM draws");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Insert some dummy data if table is empty
        $pdo->exec("INSERT INTO draws (period, winning_numbers, draw_time) VALUES
            ('2024001', '01,02,03,04,05,06', '2024-01-01 21:30:00')
        ");
    }

    // Create bets table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        numbers TEXT NOT NULL,
        period TEXT NOT NULL,
        bet_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (period) REFERENCES draws (period)
    )");

    echo "Database initialized successfully. Tables 'draws' and 'bets' are ready.";

} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage());
}
?>
