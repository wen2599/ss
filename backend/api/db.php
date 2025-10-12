<?php
// Database connection and setup

// These constants are defined in config.php, which is loaded before this script.
$servername = DB_HOST;
$username = DB_USER; // CORRECTED: Was DB_USERNAME
$password = DB_PASSWORD;
$dbname = DB_DATABASE;
$port = DB_PORT;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    // In a real app, you would log this error, not just die.
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// --- Table Creation (for setup or verification) ---

// SQL to create users table
$sql_users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

if ($conn->query($sql_users_table) === FALSE) {
    error_log("Error creating users table: " . $conn->error);
}
