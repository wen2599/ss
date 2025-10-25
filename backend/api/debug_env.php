<?php
declare(strict_types=1);

echo "<pre>";
echo "<h2>PHP Environment Debug Info</h2>";

// 1. Basic PHP info
echo "PHP SAPI Name: " . php_sapi_name() . "\n";
echo "Current Working Directory: " . getcwd() . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "error_reporting: " . error_reporting() . "\n";

// 2. Environment Variable Loading (simplified from bootstrap.php)
echo "\n<h3>.env File Loading Attempt:</h3>";
$envFound = false;
$possiblePaths = [
    __DIR__ . '/../.env',       // Path for local dev if backend is document root
    __DIR__ . '/../../.env',    // Path for local dev if project root is document root
    __DIR__ . '/.env',          // Path if .env is inside the api folder
];

$foundEnvPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $foundEnvPath = $path;
        break;
    }
}

if ($foundEnvPath) {
    echo ".env file found at: " . $foundEnvPath . "\n";
    $lines = file($foundEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, '"');
        if (!empty($name)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    $envFound = true;
    echo ".env file loaded successfully.\n";
} else {
    echo "CRITICAL: .env file not found in any expected location.\n";
}

// 3. Display loaded environment variables
echo "\n<h3>Relevant Environment Variables:</h3>";
echo "APP_DEBUG: " . ($_ENV['APP_DEBUG'] ?? 'NOT SET') . "\n";
echo "ALLOWED_ORIGINS: " . ($_ENV['ALLOWED_ORIGINS'] ?? 'NOT SET') . "\n";
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
echo "DB_DATABASE: " . ($_ENV['DB_DATABASE'] ?? 'NOT SET') . "\n";
echo "DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n";
// WARNING: Do NOT display DB_PASSWORD in production debugging!

// 4. Database Connection Test
echo "\n<h3>Database Connection Test:</h3>";
if (class_exists('PDO')) {
    $host = $_ENV['DB_HOST'] ?? null;
    $port = (int)($_ENV['DB_PORT'] ?? '3306');
    $dbname = $_ENV['DB_DATABASE'] ?? null;
    $username = $_ENV['DB_USER'] ?? null;
    $password = $_ENV['DB_PASSWORD'] ?? null; // For testing, assume it's loaded

    if ($host && $dbname && $username) {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $conn = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            echo "Database connection successful!\n";
            $conn = null; // Close connection
        } catch (PDOException $e) {
            echo "Database connection FAILED: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Database connection skipped: Missing DB environment variables.\n";
    }
} else {
    echo "PDO extension is NOT installed or enabled.\n";
}

echo "</pre>";
