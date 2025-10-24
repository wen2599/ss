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
echo "  [INFO] Bypassing .env file loading and using hardcoded credentials for this test.\n";

// Manually setting vars
$_ENV['DB_HOST'] = "mysql12.serv00.com";
$_ENV['DB_DATABASE'] = "m10300_sj";
$_ENV['DB_USER'] = "m10300_yh";
$_ENV['DB_PASSWORD'] = "Wenxiu1234*";

echo "  [OK] All required database variables are manually set.\n\n";


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
?>