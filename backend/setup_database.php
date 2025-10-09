<?php
require_once __DIR__ . '/src/core/DotEnv.php';

// Load database credentials directly from .env file
$dotenv = new DotEnv(__DIR__ . '/.env');
$env = $dotenv->getVariables();

$servername = $env['DB_HOST'] ?? null;
$username = $env['DB_USERNAME'] ?? null;
$password = $env['DB_PASSWORD'] ?? null;
$dbname = $env['DB_DATABASE'] ?? null;

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
