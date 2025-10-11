<?php
// This is a standalone server environment diagnostic script.
// It has no dependencies on other project files to ensure it can run even if the main application is broken.
header('Content-Type: text/plain; charset=utf-8');

echo "--- Server Environment Diagnostic Script ---\n\n";

// 1. Check PHP Version
echo "1. PHP Version:\n";
echo "   - Version: " . phpversion() . "\n\n";

// 2. Check Required Extensions
echo "2. Checking Required PHP Extensions:\n";
$extensions = ['mysqli', 'curl', 'json'];
$all_extensions_ok = true;
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   - [OK] '{$ext}' extension is loaded.\n";
    } else {
        echo "   - [ERROR] '{$ext}' extension is NOT loaded. This is a critical issue.\n";
        $all_extensions_ok = false;
    }
}
if ($all_extensions_ok) {
    echo "\n   Conclusion: All required extensions appear to be present.\n";
} else {
    echo "\n   Conclusion: One or more critical PHP extensions are missing. Please ask your hosting provider to install/enable them (e.g., 'php-mysql', 'php-curl').\n";
}
echo "\n";

// 3. Check .env File
echo "3. Checking .env file:\n";
// The script is in /public, so the backend root is one level up.
$dotenvPath = dirname(__DIR__) . '/.env';
echo "   - Expected Path: {$dotenvPath}\n";
if (file_exists($dotenvPath)) {
    echo "   - [OK] File exists.\n";
    if (is_readable($dotenvPath)) {
        echo "   - [OK] File is readable.\n";
    } else {
        echo "   - [ERROR] File is NOT readable. Please check file permissions (e.g., `chmod 644 .env`).\n";
    }
} else {
    echo "   - [ERROR] File does NOT exist at this path.\n";
}
echo "\n";

// 4. Test MySQLi Connection (if possible)
echo "4. Testing MySQLi Connection:\n";
if (extension_loaded('mysqli') && file_exists($dotenvPath) && is_readable($dotenvPath)) {
    // Manually parse .env to avoid dependency on DotEnv class
    $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $env[trim($name)] = trim($value);
    }

    $host = $env['DB_HOST'] ?? null;
    $user = $env['DB_USER'] ?? null;
    $pass = $env['DB_PASSWORD'] ?? null;
    $db = $env['DB_DATABASE'] ?? null;
    $port = $env['DB_PORT'] ?? 3306;

    if ($host && $user && $pass && $db) {
        echo "   - Attempting to connect to database '{$db}' at {$host}...\n";
        // Suppress warnings to handle errors gracefully
        $conn = @mysqli_connect($host, $user, $pass, $db, (int)$port);
        if ($conn) {
            echo "   - [SUCCESS] Database connection successful!\n";
            mysqli_close($conn);
        } else {
            echo "   - [ERROR] Database connection failed. Reason: " . mysqli_connect_error() . "\n";
        }
    } else {
        echo "   - [SKIP] Skipped database connection test because one or more DB variables (DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE) are missing from the parsed .env file.\n";
    }
} else {
    echo "   - [SKIP] Skipped MySQLi connection test because the 'mysqli' extension is not loaded or the .env file is missing/unreadable.\n";
}

echo "\n--- Diagnostic Complete ---\n";
?>