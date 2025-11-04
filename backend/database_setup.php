<?php
// This script should be run from the command line to set up the database tables.

require_once __DIR__ . '/config.php';

echo "--- Database Initialization Script ---\n\n";

// 1. Get Database Connection
echo "Step 1: Connecting to the database...\n";
$pdo = get_db_connection();

// Check for connection error
if (is_array($pdo) && isset($pdo['db_error'])) {
    echo "[FAILURE] Could not connect to the database. Please check your .env credentials.\n";
    echo "  Error: " . $pdo['db_error'] . "\n";
    exit(1);
}
if (!$pdo) {
    echo "[FAILURE] Could not connect to the database for an unknown reason. Please check your .env credentials and PHP error logs.\n";
    exit(1);
}
echo "  [SUCCESS] Database connection established.\n\n";

// 2. SQL statements to create tables
$sql_statements = [
    "CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(255) NOT NULL,
      `email` varchar(255) NOT NULL,
      `password_hash` varchar(255) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`),
      UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `authorized_emails` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `email` varchar(255) NOT NULL,
      `status` varchar(50) NOT NULL DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `emails` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `sender` VARCHAR(255) NOT NULL,
      `recipient` VARCHAR(255) NOT NULL,
      `subject` VARCHAR(255),
      `html_content` LONGTEXT,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `vendor_name` VARCHAR(255) DEFAULT NULL,
      `bill_amount` DECIMAL(10, 2) DEFAULT NULL,
      `currency` VARCHAR(10) DEFAULT NULL,
      `due_date` DATE DEFAULT NULL,
      `invoice_number` VARCHAR(255) DEFAULT NULL,
      `category` VARCHAR(100) DEFAULT NULL,
      `is_processed` BOOLEAN NOT NULL DEFAULT FALSE,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `lottery_results` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `lottery_type` VARCHAR(100) NOT NULL,
      `issue_number` VARCHAR(255) NOT NULL,
      `winning_numbers` VARCHAR(255) NOT NULL,
      `zodiac_signs` VARCHAR(255) NOT NULL,
      `colors` VARCHAR(255) NOT NULL,
      `drawing_date` DATE,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY `type_issue` (`lottery_type`, `issue_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

echo "Step 2: Executing SQL to create tables...\n";

foreach ($sql_statements as $sql) {
    // Extract table name from SQL
    preg_match('/CREATE TABLE IF NOT EXISTS `(.*?)`/', $sql, $matches);
    $table_name = $matches[1] ?? 'unknown_table';

    try {
        $pdo->exec($sql);
        echo "  - Table `{$table_name}` created successfully (or already existed).\n";
    } catch (PDOException $e) {
        echo "  - [FAILURE] An error occurred while creating table `{$table_name}`.\n";
        echo "    Error: " . $e->getMessage() . "\n";
        // Optionally, you can decide to stop the script on first error
        // exit(1);
    }
}

echo "\n--- Database Initialization Complete! ---\n";

?>
