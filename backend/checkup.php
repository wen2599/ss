<?php
// Set error reporting to display all issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Helper Functions ---
function check_item($title, $success, $message_success, $message_failure) {
    if ($success) {
        echo "<tr><td>{$title}</td><td class='success'>✅ SUCCESS</td><td>{$message_success}</td></tr>";
    } else {
        echo "<tr><td>{$title}</td><td class='failure'>❌ FAILURE</td><td>{$message_failure}</td></tr>";
    }
    return $success;
}

function get_masked_env($var) {
    $value = getenv($var);
    if ($value === false) return '<i>Not Set</i>';
    if (empty($value)) return '<i>Empty</i>';
    if (in_array($var, ['DB_PASSWORD', 'TELEGRAM_BOT_TOKEN', 'GEMINI_API_KEY', 'DEEPSEEK_API_KEY'])) {
        return substr($value, 0, 4) . str_repeat('*', 8) . substr($value, -4);
    }
    return htmlspecialchars($value);
}

// --- Handle Telegram Test ---
$telegram_test_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_telegram'])) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/telegram_helpers.php';
    $admin_id = getenv('TELEGRAM_ADMIN_CHAT_ID');
    if ($admin_id) {
        $test_message = "Hello from the Checkup Script! The Telegram API test was successful.";
        if (sendTelegramMessage($admin_id, $test_message)) {
            $telegram_test_result = "<p class='success'>✅ Message sent successfully! Please check your Telegram app.</p>";
        } else {
            $telegram_test_result = "<p class='failure'>❌ Failed to send message. Check `debug.log` for API errors.</p>";
        }
    } else {
        $telegram_test_result = "<p class='failure'>❌ Cannot run test: TELEGRAM_ADMIN_CHAT_ID is not set.</p>";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Health Check</title>
    <style>
        body { font-family: sans-serif; margin: 2em; background-color: #f4f4f9; color: #333; }
        h1, h2 { color: #444; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 2em; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #e9e9f0; }
        .success { color: green; font-weight: bold; }
        .failure { color: red; font-weight: bold; }
        .env-value { font-family: monospace; background-color: #eee; padding: 2px 6px; }
        code { background-color: #eee; padding: 2px 4px; border-radius: 4px; }
        button { padding: 10px 15px; font-size: 1em; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Server Health Check</h1>
    <p>This page runs a series of checks to diagnose the application's environment on the web server.</p>

    <h2>1. PHP Environment</h2>
    <table>
        <tr><th>Check</th><th>Status</th><th>Details</th></tr>
        <?php
        check_item("PHP Version", version_compare(phpversion(), '7.4', '>='), "Version: " . phpversion(), "PHP version should be 7.4 or higher. Yours is " . phpversion());
        check_item("cURL Extension", extension_loaded('curl'), "Installed.", "The <code>curl</code> extension is required for API calls.");
        check_item("PDO MySQL Extension", extension_loaded('pdo_mysql'), "Installed.", "The <code>pdo_mysql</code> extension is required for database connections.");
        ?>
    </table>

    <h2>2. File System Permissions</h2>
    <table>
        <tr><th>Check</th><th>Status</th><th>Details</th></tr>
        <?php
        $env_file = __DIR__ . '/.env';
        check_item(".env Readability", is_readable($env_file), "<code>{$env_file}</code> is readable.", "The <code>.env</code> file is missing or not readable by the web server. Check permissions.");
        $test_log_file = __DIR__ . '/permission_test.tmp';
        $can_write = @file_put_contents($test_log_file, 'test') !== false;
        if ($can_write) @unlink($test_log_file);
        check_item("Directory Writeability", $can_write, "Web server can write files in <code>" . __DIR__ . "</code>.", "The web server cannot write files (e.g., logs) in this directory. Check directory permissions (should be 755).");
        ?>
    </table>

    <h2>3. Configuration Loading</h2>
    <p>This section loads <code>config.php</code> and displays the environment variables it finds.</p>
    <table>
        <tr><th>Variable Name</th><th>Value</th></tr>
        <?php
        try {
            require_once __DIR__ . '/config.php';
            $vars_to_check = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USER', 'DB_PASSWORD', 'TELEGRAM_BOT_TOKEN', 'TELEGRAM_ADMIN_CHAT_ID'];
            foreach ($vars_to_check as $var) {
                echo "<tr><td>{$var}</td><td class='env-value'>" . get_masked_env($var) . "</td></tr>";
            }
        } catch (Throwable $e) {
            echo "<tr><td colspan='2' class='failure'>A fatal error occurred while loading <code>config.php</code>: " . $e->getMessage() . "</td></tr>";
        }
        ?>
    </table>

    <h2>4. Database Connection</h2>
    <table>
        <tr><th>Check</th><th>Status</th><th>Details</th></tr>
        <?php
        try {
            require_once __DIR__ . '/db_operations.php';
            $pdo = get_db_connection();
            $message = $pdo ? "Successfully connected. MySQL version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) : "Connection function returned null.";
            check_item("Database Connection", $pdo !== null, $message, "Connection failed. Check credentials in <code>.env</code> and ensure the database server is accessible.");
        } catch (Throwable $e) {
            check_item("Database Connection", false, "", "A fatal error occurred: " . $e->getMessage());
        }
        ?>
    </table>

    <h2>5. Telegram API Test</h2>
    <p>Click the button below to send a test message to the configured admin chat ID.</p>
    <form method="POST" action="checkup.php">
        <button type="submit" name="test_telegram">Send Test Message</button>
    </form>
    <?php
    if ($telegram_test_result) {
        echo $telegram_test_result;
    }
    ?>

</body>
</html>