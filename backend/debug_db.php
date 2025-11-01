<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

// --- Load Environment Variables ---
echo "<h2>1. Loading Environment Variables...</h2>";
require_once __DIR__ . '/env_loader.php';
echo "<p>env_loader.php included.</p>";

$host = getenv('DB_HOST');
$db = getenv('DB_DATABASE');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');

if (!$host || !$db || !$user || !$pass) {
    echo "<p style='color: red; font-weight: bold;'>ERROR: Not all database environment variables are loaded. Please check your .env file and its path.</p>";
    echo "<ul>";
    echo "<li>DB_HOST: " . ($host ? 'Loaded' : 'NOT LOADED') . "</li>";
    echo "<li>DB_DATABASE: " . ($db ? 'Loaded' : 'NOT LOADED') . "</li>";
    echo "<li>DB_USER: " . ($user ? 'Loaded' : 'NOT LOADED') . "</li>";
    echo "<li>DB_PASSWORD: " . ($pass ? 'Loaded' : 'NOT LOADED') . "</li>";
    echo "</ul>";
    exit;
} else {
    echo "<p style='color: green;'>All database environment variables are loaded successfully.</p>";
}

// --- Attempt Connection ---
echo "<h2>2. Attempting Database Connection...</h2>";
echo "<ul>";
echo "<li>Host: " . htmlspecialchars($host) . "</li>";
echo "<li>Database: " . htmlspecialchars($db) . "</li>";
echo "<li>User: " . htmlspecialchars($user) . "</li>";
echo "</ul>";

// Use mysqli directly to isolate the connection logic
$conn = new mysqli($host, $user, $pass, $db);

// --- Check Connection ---
if ($conn->connect_error) {
    echo "<p style='color: red; font-weight: bold;'>CONNECTION FAILED:</p>";
    echo "<pre style='background-color: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>" . htmlspecialchars($conn->connect_error) . "</pre>";
} else {
    echo "<p style='color: green; font-weight: bold;'>CONNECTION SUCCESSFUL!</p>";
    $conn->close();
}
