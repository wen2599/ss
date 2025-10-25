<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Bootstrap aplication ---
require_once __DIR__ . '/bootstrap.php';

global $db_connection;

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

$queries = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );",
    "CREATE TABLE IF NOT EXISTS emails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        from_address VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT,
        received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );",
    "ALTER TABLE emails ADD COLUMN extracted_data JSON DEFAULT NULL;"
];

foreach ($queries as $query) {
    if ($db_connection->query($query) === TRUE) {
        echo "Query executed successfully.\n";
    } else {
        echo "Error executing query: " . $db_connection->error . "\n";
    }
}

$db_connection->close();
