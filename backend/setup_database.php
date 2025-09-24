<?php
// A simple script to set up the database and tables.

header('Content-Type: text/plain; charset=utf-8');

// Ensure this script is not run by mistake.
// It must be triggered either from a browser with a specific query parameter
// or from the command line with a specific argument.
$is_cli = (php_sapi_name() === 'cli');
$run_from_cli = ($is_cli && isset($argv[1]) && $argv[1] === 'run');
$run_from_browser = (isset($_GET['really_run_setup']) && $_GET['really_run_setup'] === 'true');

if (!$run_from_cli && !$run_from_browser) {
    http_response_code(403);
    $message = "
        ❌ DANGER: This is a setup script that can modify your database.

        To run from a BROWSER, you must add `?really_run_setup=true` to the URL.
        To run from the COMMAND LINE (SSH), you must add the 'run' argument: `php setup_database.php run`
    ";
    // For CLI, output plain text. For browser, wrap in <pre> for readability.
    die($is_cli ? $message : "<pre>" . htmlspecialchars($message) . "</pre>");
}


require_once __DIR__ . '/config.php';

try {
    echo "🚀 Starting Database Setup...\n\n";

    // 1. Connect to MySQL server
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ 1/4: Successfully connected to MySQL server.\n";

    // 2. Create and select the database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "✅ 2/4: Database '$db_name' created or already exists.\n";
    $pdo->exec("USE `$db_name`;");
    echo "✅ 2/4: Successfully selected database '$db_name'.\n";

    // 3. Read the SQL schema file
    $sql_schema = file_get_contents(__DIR__ . '/data_table_schema.sql');
    if ($sql_schema === false) {
        throw new Exception("Could not read data_table_schema.sql file.");
    }
    echo "✅ 3/4: Successfully read schema file.\n";

    // 4. Execute the SQL to create tables
    $pdo->exec($sql_schema);
    echo "✅ 4/4: Tables created successfully (if they didn't already exist).\n";

    echo "\n🎉 Database setup complete! You should delete or rename this file for security.\n";

} catch (PDOException $e) {
    http_response_code(500);
    die("❌ DATABASE ERROR: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    http_response_code(500);
    die("❌ GENERAL ERROR: " . $e->getMessage() . "\n");
}
?>