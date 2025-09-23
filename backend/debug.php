<?php
// This script is for diagnosing server environment issues.
// It should be accessed directly in the browser.

// --- Helper Functions ---
function check_status($condition, $success_message, $failure_message) {
    if ($condition) {
        echo "[OK] " . $success_message . "<br>";
        return true;
    } else {
        echo "[FAIL] " . $failure_message . "<br>";
        return false;
    }
}

// Set content type to HTML for better readability
header('Content-Type: text/html; charset=utf-8');
echo "<h1>Server Environment Diagnostic</h1>";
echo "<pre>";

// --- Check 1: PHP Version ---
echo "<h2>1. PHP Version Check</h2>";
check_status(version_compare(PHP_VERSION, '7.4.0', '>='), "PHP version is " . PHP_VERSION . " (>= 7.4.0)", "PHP version is " . PHP_VERSION . ". Application may require PHP 7.4 or higher.");
echo "<hr>";

// --- Check 2: Config File Readability ---
echo "<h2>2. Configuration File Check</h2>";
$config_path = __DIR__ . '/config.php';
if (check_status(file_exists($config_path), "config.php exists at: {$config_path}", "config.php does not exist at: {$config_path}")) {
    if (check_status(is_readable($config_path), "config.php is readable.", "config.php is NOT readable. Check file permissions.")) {
        require_once $config_path;
        echo "[INFO] config.php loaded.<br>";
    }
}
echo "<hr>";

// --- Check 3: Database Connectivity ---
echo "<h2>3. Database Connection Check</h2>";
if (isset($db_host, $db_name, $db_user, $db_pass)) {
    echo "[INFO] Database credentials found in config.php.<br>";
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        check_status(true, "Successfully connected to the database '{$db_name}' on host '{$db_host}'.", "");
    } catch (PDOException $e) {
        check_status(false, "", "Failed to connect to the database. Error: " . $e->getMessage());
    }
} else {
    check_status(false, "", "Database credentials (db_host, db_name, db_user, db_pass) are not set in config.php.");
}
echo "<hr>";

// --- Check 4: Directory Readability ---
echo "<h2>4. Directory and File Permissions Check</h2>";
$actions_dir = __DIR__ . '/actions';
if(check_status(file_exists($actions_dir) && is_dir($actions_dir), "/actions directory exists.", "/actions directory does NOT exist.")) {
    check_status(is_readable($actions_dir), "/actions directory is readable.", "/actions directory is NOT readable. Check permissions.");
}
$login_action_file = $actions_dir . '/login.php';
if(check_status(file_exists($login_action_file), "/actions/login.php file exists.", "/actions/login.php file does NOT exist.")) {
    check_status(is_readable($login_action_file), "/actions/login.php file is readable.", "/actions/login.php file is NOT readable. Check permissions.");
}

echo "</pre>";
echo "<h2>Diagnosis Complete</h2>";
?>
