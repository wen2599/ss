<?php
// backend/setup_database.php
// Version 2.0: Aligned with refactored backend API and auth system.

// This script is intended for command-line execution.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/env_loader.php';

// --- Database Connection ---
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_password = getenv('DB_PASSWORD');
$db_name = getenv('DB_NAME');

if (!$db_host || !$db_user || !$db_name) {
    die("Error: Database credentials (DB_HOST, DB_USER, DB_NAME) are not fully set in .env file.\n");
}

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}
echo "Database connected successfully.\n";

// --- Table: lottery_results ---
$sql_lottery = "
CREATE TABLE IF NOT EXISTS lottery_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lottery_type VARCHAR(255) NULL,
    issue_number VARCHAR(50) UNIQUE NOT NULL,
    draw_date DATE,
    numbers VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";
if ($conn->query($sql_lottery)) {
    echo "Table 'lottery_results' is up to date.\n";
} else {
    echo "Error with 'lottery_results' table: " . $conn->error . "\n";
}

// --- Table: users ---
$sql_users = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";
if ($conn->query($sql_users)) {
    echo "Table 'users' is up to date.\n";
    // Migration: Check if the old 'password' column exists and rename it.
    $result = $conn->query("SHOW COLUMNS FROM `users` LIKE 'password'");
    if ($result->num_rows > 0) {
        echo "Found old 'password' column. Attempting to rename to 'password_hash'...\n";
        if ($conn->query("ALTER TABLE users CHANGE COLUMN password password_hash VARCHAR(255) NOT NULL")) {
            echo "Successfully renamed 'password' to 'password_hash'.\n";
        } else {
            echo "Error renaming column: " . $conn->error . "\n";
        }
    }
} else {
    echo "Error with 'users' table: " . $conn->error . "\n";
}

// --- Table: emails ---
$sql_emails = "
CREATE TABLE IF NOT EXISTS emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    from_address VARCHAR(255) NOT NULL,
    subject TEXT,
    body_text LONGTEXT,
    body_html LONGTEXT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
);";
if ($conn->query($sql_emails)) {
    echo "Table 'emails' is up to date.\n";
} else {
    echo "Error with 'emails' table: " . $conn->error . "\n";
}

// --- Table: tokens (for authentication) ---
$sql_tokens = "
CREATE TABLE IF NOT EXISTS tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token)
);";
if ($conn->query($sql_tokens)) {
    echo "Table 'tokens' is up to date.\n";
} else {
    echo "Error with 'tokens' table: " . $conn->error . "\n";
}

$conn->close();
echo "Database setup script finished.\n";
