<?php
require_once __DIR__ . '/src/core/DotEnv.php';

// Load environment variables
(new DotEnv(__DIR__ . '/.env'))->load();

// Database credentials from environment variables
$servername = $_ENV['DB_HOST'] ?? null;
$username = $_ENV['DB_USERNAME'] ?? null;
$password = $_ENV['DB_PASSWORD'] ?? null;
$dbname = $_ENV['DB_DATABASE'] ?? null;

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
