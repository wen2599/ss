<?php

// --- Permission and Environment Diagnostic Script ---
header('Content-Type: text/plain');

echo "--- Web Server Permission & Environment Diagnostic ---\n\n";

// --- 1. Check Current User ---
echo "1. Checking the user the web server is running as...\n";
// The `whoami` command might not be available or allowed.
// `get_current_user()` is a more reliable PHP function.
$currentUser = get_current_user();
if (!empty($currentUser)) {
    echo "✅ Web server is running as user: '{$currentUser}'\n\n";
} else {
    echo "⚠️ Could not determine the web server user. This is not a fatal error.\n\n";
}

// --- 2. Check .env File Permissions ---
echo "2. Checking .env file readability for this user...\n";
$envPath = __DIR__ . '/.env';

if (file_exists($envPath)) {
    echo "   - .env file exists at: {$envPath}\n";
    if (is_readable($envPath)) {
        echo "✅ SUCCESS: The .env file is readable by the web server user.\n\n";
    } else {
        echo "❌ CRITICAL ERROR: The .env file is NOT readable by the web server user.\n";
        echo "   Please check the file permissions. A common setting is 644.\n\n";
        exit;
    }
} else {
    echo "❌ CRITICAL ERROR: The .env file does not exist at {$envPath}.\n\n";
    exit;
}

// --- 3. Load and Display Environment Variables ---
echo "3. Attempting to load and display variables from .env...\n";
// Use the same loading logic as the main application
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    list($name, $value) = explode('=', $line, 2);
    $value = trim(trim($value), '"');
    // Use both putenv and $_ENV for maximum compatibility
    putenv(trim($name) . '=' . $value);
    $_ENV[trim($name)] = $value;
}

// Now check what was loaded
$db_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? null);
$db_port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? null);
$db_name = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? null);
$db_user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? null);
$db_pass_is_set = !empty(getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? null));

echo "   - DB_HOST: " . ($db_host ?: 'NOT LOADED') . "\n";
echo "   - DB_PORT: " . ($db_port ?: 'NOT LOADED') . "\n";
echo "   - DB_DATABASE: " . ($db_name ?: 'NOT LOADED') . "\n";
echo "   - DB_USER: " . ($db_user ?: 'NOT LOADED') . "\n";
echo "   - DB_PASSWORD: " . ($db_pass_is_set ? '****** (is set)' : '(is NOT set)') . "\n\n";

if (empty($db_host) || empty($db_port) || empty($db_name) || empty($db_user)) {
    echo "❌ CRITICAL ERROR: One or more required database variables failed to load into the environment.\n";
    echo "   This can be caused by server security settings (like `variables_order` in php.ini) that prevent `putenv` or `$_ENV` from working as expected.\n";
} else {
    echo "✅ SUCCESS: All required database variables appear to be loaded correctly.\n";
}

echo "\n--- Diagnosis Complete ---\n";

?>