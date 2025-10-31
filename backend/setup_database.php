<?php
// backend/setup_database.php

// This script should be executed from the command line via SSH:
// php /path/to/your/project/backend/setup_database.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load environment variables using the shared loader
require_once __DIR__ . '/env_loader.php';

// Database connection details from environment variables
$db_host = $_ENV['DB_HOST'] ?? null;
$db_user = $_ENV['DB_USER'] ?? null;
$db_pass = $_ENV['DB_PASS'] ?? null;
$db_name = $_ENV['DB_NAME'] ?? null;

if (!$db_host || !$db_user || !$db_pass || !$db_name) {
    die("Error: Database credentials are not fully set in your .env file.\n");
}

// Create a new database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

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
} else {
    echo "Error creating table 'lottery_results': " . $conn->error . "\n";
}

// --- Create emails table ---
$sql_emails = "
CREATE TABLE IF NOT EXISTS emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) UNIQUE,
    from_address VARCHAR(255) NOT NULL,
    subject TEXT,
    body_text LONGTEXT,
    body_html LONGTEXT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

if ($conn->query($sql_emails) === TRUE) {
    echo "Table 'emails' created or already exists.\n";
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
