<?php
// backend/setup_database.php

// This script should be executed from the command line via SSH:
// php /path/to/your/project/backend/setup_database.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load environment variables using the shared loader
require_once __DIR__ . '/env_loader.php';

// Explicitly call load_env() and capture its return value
$env_vars = load_env();

echo "DEBUG: setup_database.php: Environment variables loaded by load_env() function.\n";
echo "DEBUG: setup_database.php: DB_HOST from returned array: " . ($env_vars['DB_HOST'] ?? 'NOT SET') . "\n";
echo "DEBUG: setup_database.php: DB_USER from returned array: " . ($env_vars['DB_USER'] ?? 'NOT SET') . "\n";
echo "DEBUG: setup_database.php: DB_PASSWORD from returned array: " . ($env_vars['DB_PASSWORD'] ?? 'NOT SET') . "\n"; // Corrected to DB_PASSWORD
echo "DEBUG: setup_database.php: DB_NAME from returned array: " . ($env_vars['DB_NAME'] ?? 'NOT SET') . "\n";

// Database connection details from the returned environment variables array
$db_host = $env_vars['DB_HOST'] ?? null;
$db_user = $env_vars['DB_USER'] ?? null;
$db_password = $env_vars['DB_PASSWORD'] ?? null;
$db_name = $env_vars['DB_NAME'] ?? null;

if (!$db_host || !$db_user || !$db_password || !$db_name) {
    die("Error: Database credentials are not fully set in your .env file or could not be loaded from env_loader.\n");
}

// Create a new database connection
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Database connected successfully.\n";

// --- Create lottery_results table ---
$sql_lottery = "
CREATE TABLE IF NOT EXISTS lottery_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_number VARCHAR(50) UNIQUE NOT NULL,
    draw_date DATE,
    numbers VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

if ($conn->query($sql_lottery) === TRUE) {
    echo "Table 'lottery_results' created or already exists.\n";

    // --- Safe migration to add 'lottery_type' column ---
    $result = $conn->query("SHOW COLUMNS FROM `lottery_results` LIKE 'lottery_type'");
    if ($result->num_rows == 0) {
        echo "Attempting to add 'lottery_type' column to 'lottery_results' table...\n";
        $alter_sql = "ALTER TABLE `lottery_results` ADD COLUMN `lottery_type` VARCHAR(255) NULL AFTER `id`";
        if ($conn->query($alter_sql) === TRUE) {
            echo "Successfully added 'lottery_type' column to 'lottery_results' table.\n";
        } else {
            echo "Error adding 'lottery_type' column: " . $conn->error . "\n";
        }
    } else {
        echo "'lottery_type' column already exists in 'lottery_results' table. No migration needed.\n";
    }
} else {
    echo "Error creating table 'lottery_results': " . $conn->error . "\n";
}

// --- Create emails table (with raw_content field) ---
$sql_emails = "
CREATE TABLE IF NOT EXISTS emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) UNIQUE,
    from_address VARCHAR(255) NOT NULL,
    subject TEXT,
    body_text LONGTEXT,
    body_html LONGTEXT,
    raw_content MEDIUMTEXT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

if ($conn->query($sql_emails) === TRUE) {
    echo "Table 'emails' created or already exists.\n";

    // --- Safe migration to add 'user_id' column to 'emails' table ---
    $result = $conn->query("SHOW COLUMNS FROM `emails` LIKE 'user_id'");
    if ($result->num_rows == 0) {
        echo "Attempting to add 'user_id' column to 'emails' table...\n";
        $alter_sql = "ALTER TABLE `emails` ADD COLUMN `user_id` INT NULL AFTER `id`, ADD INDEX `idx_user_id` (`user_id`)";
        if ($conn->query($alter_sql) === TRUE) {
            echo "Successfully added 'user_id' column and index to 'emails' table.\n";
        } else {
            echo "Error adding 'user_id' column: " . $conn->error . "\n";
        }
    } else {
        echo "'user_id' column already exists in 'emails' table. No migration needed.\n";
    }

} else {
    echo "Error creating table 'emails': " . $conn->error . "\n";
}

// --- Create users table ---
$sql_users = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

if ($conn->query($sql_users) === TRUE) {
    echo "Table 'users' created or already exists.\n";
} else {
    echo "Error creating table 'users': " . $conn->error . "\n";
}

$conn->close();
echo "Setup script finished.\n";
