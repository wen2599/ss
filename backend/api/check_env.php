<?php
// --- Environment Health Check ---
// This script is designed to run independently to diagnose server environment issues.

header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "--- Backend Environment Health Check ---\n\n";

// --- 1. PHP Version Check ---
echo "[1/5] Checking PHP Version...\n";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "  [OK] PHP version is " . PHP_VERSION . ".\n\n";
} else {
    echo "  [ERROR] PHP version is " . PHP_VERSION . ". Application requires 7.4.0 or higher.\n\n";
    exit;
}

// --- 2. Environment Variable Loading ---
echo "[2/5] Loading Environment Variables...\n";

// List of possible paths for the .env file
$possibleEnvPaths = [
    __DIR__ . '/../../.env', // Standard project root from /api
    '/usr/home/wenge95222/domains/wenge.cloudns.ch/private_html/.env' // Production server path
];

$envPath = null;
foreach ($possibleEnvPaths as $path) {
    if (file_exists($path)) {
        $envPath = $path;
        break;
    }
}

if (is_null($envPath)) {
    echo "  [ERROR] .env file not found in any of the expected locations.\n";
    echo "  Checked paths:\n";
    foreach ($possibleEnvPaths as $path) {
        echo "    - {$path}\n";
    }
    echo "\n";
    exit;
}
echo "  [OK] Found .env file at: {$envPath}\n";

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    echo "  [ERROR] Could not read the .env file. Check file permissions.\n\n";
    exit;
}

$requiredVars = ['DB_HOST', 'DB_DATABASE', 'DB_USER', 'DB_PASSWORD'];
$loadedVars = [];
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        if (!empty($name)) {
            $_ENV[$name] = trim($value, '"');
            $loadedVars[] = $name;
        }
    }
}
echo "  [OK] .env file loaded successfully.\n";

$missingVars = array_diff($requiredVars, $loadedVars);
if (empty($missingVars)) {
    echo "  [OK] All required database variables (DB_HOST, DB_DATABASE, DB_USER, DB_PASSWORD) are present.\n\n";
} else {
    echo "  [ERROR] The following required environment variables are missing from your .env file: " . implode(', ', $missingVars) . "\n\n";
    exit;
}

// --- 3. PHP Extension Check ---
echo "[3/5] Checking Required PHP Extensions...\n";
if (extension_loaded('pdo_mysql')) {
    echo "  [OK] 'pdo_mysql' extension is loaded.\n\n";
} else {
    echo "  [ERROR] 'pdo_mysql' extension is NOT loaded. This is a fatal error for the application.\n";
    echo "  Please enable it in your php.ini file.\n\n";
    exit;
}

// --- 4. Database Connection Test ---
echo "[4/5] Testing Database Connection...\n";
$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_DATABASE'];
$dbUser = $_ENV['DB_USER'];
$dbPass = $_ENV['DB_PASSWORD'];
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    echo "  [SUCCESS] Successfully connected to the database '{$dbName}' on '{$dbHost}'.\n\n";
} catch (PDOException $e) {
    echo "  [ERROR] Database connection failed!\n";
    echo "  DSN: {$dsn}\n";
    echo "  Error Message: " . $e->getMessage() . "\n";
    echo "  Please check your DB credentials in the .env file and ensure the database server is running and accessible.\n\n";
    exit;
}

// --- 5. Final Check ---
echo "[5/5] Checking for `users` table...\n";
try {
    $stmt = $pdo->query("SELECT 1 FROM `users` LIMIT 1");
    echo "  [OK] The `users` table exists.\n\n";
} catch (Exception $e) {
    echo "  [ERROR] Could not find the `users` table.\n";
    echo "  Error: " . $e->getMessage() . "\n";
    echo "  Have you run the `setup.php` script to create the database tables?\n\n";
    exit;
}


echo "--- Health Check Complete: Your environment appears to be correctly configured! ---";
