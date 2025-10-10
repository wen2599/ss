<?php
require_once __DIR__ . '/src/core/DotEnv.php';

// Load database credentials directly from the project root's .env file
$dotenv = new DotEnv(dirname(__DIR__) . '/.env');
$env = $dotenv->getVariables();

$servername = $env['DB_HOST'] ?? null;
$username = $env['DB_USER'] ?? null;
$password = $env['DB_PASSWORD'] ?? null;
$dbname = $env['DB_DATABASE'] ?? null;

// Validate that the required database credentials are set
if (empty($servername) || empty($username) || empty($dbname)) {
    die("Error: Database credentials are not configured.\nPlease create a '.env' file in the 'backend' directory with the following content (based on .env.example):\n\n" .
        "DB_HOST=your_database_host\n" .
        "DB_USER=your_database_user\n" .
        "DB_PASSWORD=your_database_password\n" .
        "DB_DATABASE=your_database_name\n\n" .
        "The script could not find the required credentials to connect to the database.\n");
}

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