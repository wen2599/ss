<?php
// Database connection and setup

$servername = "127.0.0.1";
$username = "root";
$password = "password";
$dbname = "test_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Table Creation ---

// SQL to create users table (assuming you have one)
$sql_users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

if ($conn->query($sql_users_table) === FALSE) {
    echo "Error creating table: " . $conn->error;
}

// SQL to create emails table
$sql_emails_table = "CREATE TABLE IF NOT EXISTS emails (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) UNIQUE,
    `from` VARCHAR(255),
    `to` VARCHAR(255),
    subject VARCHAR(255),
    text_content TEXT,
    html_content MEDIUMTEXT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

if ($conn->query($sql_emails_table) === FALSE) {
    echo "Error creating table: " . $conn->error;
}

// --- Helper Functions ---

/**
 * A simple function to return a JSON response.
 */
function json_response($data, $success = true, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'data' => $data]);
    exit();
}

?>
