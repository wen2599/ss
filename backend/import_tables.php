<?php
// Run only from CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Re-enable our .env loader
require_once __DIR__ . '/utils/env_loader.php';
load_env(__DIR__ . '/.env');

// Read credentials from the environment variables loaded by our fixed loader
$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USER'];
$dbPass = $_ENV['DB_PASS'];

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connected successfully using .env file.\n";

    $sql = "
    CREATE TABLE IF NOT EXISTS `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `email` VARCHAR(255) NOT NULL UNIQUE,
      `password_hash` VARCHAR(255) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `raw_emails` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `raw_content` LONGTEXT NOT NULL,
      `status` ENUM('pending', 'processing', 'processed', 'error') DEFAULT 'pending',
      `ai_result` JSON NULL,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `bet_slips` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `email_id` INT NOT NULL,
      `user_id` INT NOT NULL,
      `slip_data` JSON NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (email_id) REFERENCES raw_emails(id) ON DELETE CASCADE,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `telegram_user_id` VARCHAR(255) NOT NULL UNIQUE,
        `name` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo "Tables created or already exist successfully.\n";

} catch (PDOException $e) {
    die("Could not connect to the database $dbName :" . $e->getMessage());
}
