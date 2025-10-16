<?php
// backend/check_db.php

// Enable full error reporting for this diagnostic script
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set content type to plain text for clear output in browser or terminal
header('Content-Type: text/plain; charset=utf-8');

echo "--- Database Connection Diagnostic Script ---\n\n";

// --- Step 1: Load .env File ---
echo "Step 1: Loading .env file...\n";
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath) || !is_readable($envPath)) {
    die("Error: .env file not found or is not readable at '{$envPath}'. Please ensure it exists and has correct permissions.\n");
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    die("Error: Could not read the .env file.\n");
}

foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        // Use putenv and $_ENV for broad compatibility
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}
echo "OK: .env file loaded.\n\n";

// --- Step 2: Check and Display DB Credentials ---
echo "Step 2: Reading database credentials from environment...\n";
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_DATABASE');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');

$all_vars_present = true;
if (!$host) { echo "Error: DB_HOST is not set.\n"; $all_vars_present = false; }
if (!$port) { echo "Error: DB_PORT is not set.\n"; $all_vars_present = false; }
if (!$dbname) { echo "Error: DB_DATABASE is not set.\n"; $all_vars_present = false; }
if (!$user) { echo "Error: DB_USER is not set.\n"; $all_vars_present = false; }
if (!$pass) { echo "Warning: DB_PASSWORD is not set. This might be okay for local development.\n"; }

if (!$all_vars_present) {
    die("\nError: One or more required database variables are missing from your .env file. Please check and try again.\n");
}

echo "OK: All required variables are present.\n";
echo "  - Host: {$host}\n";
echo "  - Port: {$port}\n";
echo "  - Database: {$dbname}\n";
echo "  - User: {$user}\n\n";

// --- Step 3: Attempt Database Connection ---
echo "Step 3: Attempting to connect to the database...\n";
$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5, // 5 second timeout
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    echo "--- DIAGNOSIS: SUCCESS! ---\n";
    echo "Successfully connected to the database '{$dbname}' on host '{$host}'.\n\n";

    // Optional: Print server version for confirmation
    $server_info = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "Database server version: {$server_info}\n";

} catch (PDOException $e) {
    echo "--- DIAGNOSIS: FAILED ---\n";
    echo "Could not connect to the database. The database server returned an error.\n\n";
    echo "PDOException Code: " . $e->getCode() . "\n";
    echo "PDOException Message: " . $e->getMessage() . "\n\n";
    echo "Common causes for this error:\n";
    echo "1. Incorrect credentials (DB_USER, DB_PASSWORD) in your .env file.\n";
    echo "2. The database server is not running or is not accessible from the web server.\n";
    echo "3. A firewall is blocking the connection on the specified port ({$port}).\n";
    echo "4. The database '{$dbname}' does not exist on the server.\n";
    echo "5. The user '{$user}' does not have permission to connect to the database from this host.\n";
}
?>