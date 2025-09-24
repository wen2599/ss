<?php
// A simple script to set up the database and tables.

header('Content-Type: text/plain; charset=utf-8');

// Ensure this script is not run in a production environment by mistake
// by checking for a specific query parameter.
if (!isset($_GET['really_run_setup']) || $_GET['really_run_setup'] !== 'true') {
    http_response_code(403);
    die("
        ❌ DANGER: This is a setup script. It can modify your database structure.
        To run it, you must add `?really_run_setup=true` to the end of the URL.
    ");
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