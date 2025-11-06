<?php
// backend/database_setup.php

// This script is intended to be run from the command line.
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once 'config.php'; // Load config first

try {
    require_once 'database.php';
    $pdo = Database::getConnection();
    echo "Successfully connected to the database.\n";

    // Table: users
    $sql_users = "
    CREATE TABLE IF NOT EXISTS `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `email` VARCHAR(255) NOT NULL UNIQUE,
      `password` VARCHAR(255) NOT NULL,
      `auth_token` VARCHAR(255) NULL,
      `token_expires_at` DATETIME NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_users);
    echo "Table 'users' created or already exists.\n";

    // Table: emails
    $sql_emails = "
    CREATE TABLE IF NOT EXISTS `emails` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `from_email` VARCHAR(255) NOT NULL,
      `to_email` VARCHAR(255) NOT NULL,
      `subject` VARCHAR(255) NOT NULL,
      `body` TEXT NOT NULL,
      `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_emails);
    echo "Table 'emails' created or already exists.\n";

    // Table: lottery_results
    $sql_lottery_results = "
    CREATE TABLE IF NOT EXISTS `lottery_results` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lottery_type` VARCHAR(50) NULL,
        `issue_number` VARCHAR(50) NULL,
        `numbers` VARCHAR(255) NOT NULL,
        `draw_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `issue_type` (`lottery_type`, `issue_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_lottery_results);
    echo "Table 'lottery_results' created or already exists.\n";

    // Table: auth_users
    $sql_auth_users = "
    CREATE TABLE IF NOT EXISTS `auth_users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `email` VARCHAR(255) NOT NULL UNIQUE,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_auth_users);
    echo "Table 'auth_users' created or already exists.\n";

    // --- Legacy Table Rename (Compatible with older MySQL) ---
    // Check if the old table 'lottery_numbers' exists before trying to rename it.
    $db_name = get_env_variable('DB_NAME');
    if ($db_name) {
        $sql_check_table = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = 'lottery_numbers'";
        $stmt = $pdo->prepare($sql_check_table);
        $stmt->execute([$db_name]);
        $table_exists = $stmt->fetchColumn() > 0;

        if ($table_exists) {
            $pdo->exec("RENAME TABLE `lottery_numbers` TO `lottery_results_old`");
            echo "Renamed old 'lottery_numbers' table to 'lottery_results_old'.\n";
        }
    }

    echo "\nDatabase setup completed successfully.\n";

} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("An unexpected error occurred: ". $e->getMessage(). "\n");
}
?>
