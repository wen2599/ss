<?php
// --- Database Initializer CLI Script ---
// This script should be run from the command line (e.g., `php initialize_database.php`)
// from within the `backend` directory.

// Change to the script's directory to ensure correct relative paths.
chdir(__DIR__);

// --- Environment Loading ---
function loadEnvForCli() {
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) {
        echo "\033[31mError: .env file not found at {$envPath}. Please create it first.\033[0m\n";
        exit(1);
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, '"');
    }
}

loadEnvForCli();

// --- Configuration ---
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_user = $_ENV['DB_USER'] ?? '';
$db_pass = $_ENV['DB_PASSWORD'] ?? '';
$db_name = $_ENV['DB_DATABASE'] ?? '';
$sql_file = __DIR__ . '/database_schema.sql';

if (empty($db_user) || empty($db_name)) {
    echo "\033[31mError: DB_USER and DB_DATABASE must be set in your .env file.\033[0m\n";
    exit(1);
}

// --- Main Logic ---
echo "\033[34mStarting database initialization...\033[0m\n";

// Step 1: Connect to MySQL server without selecting a database.
$mysqli = new mysqli($db_host, $db_user, $db_pass);
if ($mysqli->connect_error) {
    echo "\033[31mConnection Failed: " . $mysqli->connect_error . "\033[0m\n";
    exit(1);
}
echo "\033[32mSuccessfully connected to MySQL server.\033[0m\n";

// Step 2: Create the database if it doesn't exist.
$createDbQuery = "CREATE DATABASE IF NOT EXISTS `" . $mysqli->real_escape_string($db_name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
if ($mysqli->query($createDbQuery)) {
    echo "\033[32mDatabase '{$db_name}' created or already exists.\033[0m\n";
} else {
    echo "\033[31mError creating database: " . $mysqli->error . "\033[0m\n";
    $mysqli->close();
    exit(1);
}

// Step 3: Select the database.
$mysqli->select_db($db_name);

// Step 4: Read and execute the SQL schema file.
if (!file_exists($sql_file)) {
    echo "\033[31mError: SQL schema file not found at {$sql_file}.\033[0m\n";
    $mysqli->close();
    exit(1);
}

$sql = file_get_contents($sql_file);
if ($mysqli->multi_query($sql)) {
    // Important to clear results from multi_query
    while ($mysqli->next_result()) {
        if (!$mysqli->more_results()) break;
    }
    echo "\033[32mSuccessfully imported table schema from {$sql_file}.\033[0m\n";
} else {
    echo "\033[31mError importing schema: " . $mysqli->error . "\033[0m\n";
    $mysqli->close();
    exit(1);
}

$mysqli->close();
echo "\033[32mDatabase initialization complete!\033[0m\n";

?>
