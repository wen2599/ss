<?php
// Database connection and setup

// These constants are defined in config.php, which is loaded before this script.
$servername = DB_HOST;
$username = DB_USER; // 修正为 DB_USER
$password = DB_PASSWORD;
$dbname = DB_DATABASE;
$port = DB_PORT;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// --- Table Creation (for setup or verification) ---

// SQL to create users table
$sql_users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

if ($conn->query($sql_users_table) === FALSE) {
    error_log("Error creating users table: " . $conn->error);
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
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

if ($conn->query($sql_emails_table) === FALSE) {
    error_log("Error creating emails table: " . $conn->error);
}

// --- Helper Functions ---
if (!function_exists('json_response')) {
    function json_response($data, $success = true, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'data' => $data]);
        exit();
    }
}
?>
