<?php

// --- Standalone Diagnostic Script ---
// This script is designed to be run from the command line (CLI)
// to diagnose environment and connection issues.

header('Content-Type: text/plain');

echo "--- PHP Environment Diagnostic Script ---\n\n";

// --- 1. Check for Required PHP Extensions ---
echo "1. Checking for required PHP extensions...\n";
$required_extensions = ['pdo_mysql', 'curl'];
$missing_extensions = [];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (empty($missing_extensions)) {
    echo "✅ SUCCESS: All required extensions (pdo_mysql, curl) are loaded.\n\n";
} else {
    echo "❌ ERROR: The following required PHP extensions are NOT loaded: " . implode(', ', $missing_extensions) . "\n";
    echo "Please enable them in your php.ini file.\n\n";
    exit; // Stop further tests if extensions are missing
}

// --- 2. Check .env File ---
echo "2. Checking for .env file...\n";
$envPath = __DIR__ . '/.env';
if (is_readable($envPath)) {
    echo "✅ SUCCESS: .env file found and is readable at: {$envPath}\n\n";
} else {
    echo "❌ ERROR: .env file is missing or not readable at: {$envPath}\n";
    echo "Please ensure the file exists and has the correct permissions.\n\n";
    exit;
}

// --- 3. Load and Display Environment Variables ---
echo "3. Loading and displaying environment variables from .env...\n";
// Manually load the .env file for this script
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    list($name, $value) = explode('=', $line, 2);
    $value = trim(trim($value), '"');
    putenv(trim($name) . '=' . $value);
}

$db_host = getenv('DB_HOST');
$db_port = getenv('DB_PORT');
$db_name = getenv('DB_DATABASE');
$db_user = getenv('DB_USER');
$db_pass_is_set = !empty(getenv('DB_PASSWORD'));

echo "   - DB_HOST: {$db_host}\n";
echo "   - DB_PORT: {$db_port}\n";
echo "   - DB_DATABASE: {$db_name}\n";
echo "   - DB_USER: {$db_user}\n";
echo "   - DB_PASSWORD: " . ($db_pass_is_set ? '****** (is set)' : '(is NOT set)') . "\n\n";

$missingVars = [];
if (empty($db_host)) $missingVars[] = 'DB_HOST';
if (empty($db_port)) $missingVars[] = 'DB_PORT';
if (empty($db_name)) $missingVars[] = 'DB_DATABASE';
if (empty($db_user)) $missingVars[] = 'DB_USER';

if (!empty($missingVars)) {
    echo "❌ ERROR: The following required variables are empty in your .env file: " . implode(', ', $missingVars) . "\n\n";
    exit;
} else {
    echo "✅ SUCCESS: All required database variables are present.\n\n";
}


// --- 4. Attempt Database Connection ---
echo "4. Attempting to connect to the database...\n";
try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, getenv('DB_PASSWORD'), $options);
    echo "✅ SUCCESS: Database connection was successful!\n\n";
    echo "--- Diagnosis Complete ---\n";
    echo "If this script succeeds but your web application still fails, the issue is likely related to your web server's configuration (e.g., PHP-FPM user permissions, security policies like SELinux, or incorrect web root).";

} catch (PDOException $e) {
    echo "❌ ERROR: Database connection failed!\n";
    echo "   - PDO Error Code: " . $e->getCode() . "\n";
    echo "   - PDO Error Message: " . $e->getMessage() . "\n\n";
    echo "This indicates a problem with your database credentials, server availability, or firewall rules between the application and the database.\n";
}

?>