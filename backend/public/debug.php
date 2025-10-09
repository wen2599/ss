<?php

// --- Ultimate Debugger ---
// This script has zero dependencies on the application's files.
// It will test the fundamental steps: finding .env, parsing it, and connecting to the DB.

header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "--- DATABASE CONNECTION DEBUGGER ---\n\n";

// --- Step 1: Define the DotEnv class directly ---
// This removes any dependency on require_once.
class DotEnvForDebug
{
    protected $path;
    public function __construct(string $path) {
        if (!file_exists($path)) { throw new \InvalidArgumentException(sprintf('File does not exist at path: %s', $path)); }
        $this->path = $path;
    }
    public function getVariables() :array {
        if (!is_readable($this->path)) { throw new \RuntimeException(sprintf('File is not readable at path: %s', $this->path)); }
        $variables = [];
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) { continue; }
            if (strpos($line, '=') === false) { continue; }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $variables[$name] = $value;
        }
        return $variables;
    }
}
echo "[OK] DotEnv class defined.\n";

// --- Step 2: Locate and parse the .env file ---
// The path is calculated from this file's location in `public` up to the project root.
$dotenvPath = __DIR__ . '/../../.env';
echo "[INFO] Looking for .env file at: " . realpath(dirname($dotenvPath)) . '/' . basename($dotenvPath) . "\n";

if (!file_exists($dotenvPath)) {
    die("[FATAL] .env file not found. Script terminated.");
}
echo "[OK] .env file found.\n";

try {
    $dotenv = new DotEnvForDebug($dotenvPath);
    $env = $dotenv->getVariables();
    echo "[OK] .env file parsed successfully.\n";
} catch (Exception $e) {
    die("[FATAL] Failed to parse .env file: " . $e->getMessage());
}

// --- Step 3: Attempt to connect to the database ---
$host = $env['DB_HOST'] ?? null;
$user = $env['DB_USER'] ?? null;
$pass = $env['DB_PASSWORD'] ?? null;
$db = $env['DB_DATABASE'] ?? null;
$port = $env['DB_PORT'] ?? 3306;

echo "[INFO] Attempting to connect with the following credentials:\n";
echo "  - Host: " . ($host ? $host : 'NOT SET') . "\n";
echo "  - Database: " . ($db ? $db : 'NOT SET') . "\n";
echo "  - User: " . ($user ? $user : 'NOT SET') . "\n";
echo "  - Port: " . $port . "\n";

if (!$host || !$user || !$db) {
    die("[FATAL] One or more database variables are missing from the .env file. Script terminated.");
}

// Use mysqli directly to avoid any other dependencies
$conn = new mysqli($host, $user, $pass, $db, (int)$port);

// Check for connection errors
if ($conn->connect_error) {
    die("[FATAL] Connection Failed: " . $conn->connect_error);
}

echo "[SUCCESS] Database connection was successful!\n\n";
echo "--- DEBUGGING COMPLETE ---";

$conn->close();