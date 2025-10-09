<?php
require_once __DIR__ . '/src/core/DotEnv.php';

// Load environment variables
(new DotEnv(__DIR__ . '/.env'))->load();

// Database credentials from environment variables
$servername = getenv('DB_HOST');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$dbname = getenv('DB_NAME');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read SQL file
$sql = file_get_contents(__DIR__ . '/database/create_lottery_table.sql');

// Execute multi query
if ($conn->multi_query($sql)) {
    echo "Database setup successful!\n";
} else {
    echo "Error setting up database: " . $conn->error . "\n";
}

$conn->close();
