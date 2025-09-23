<?php
// A simple script to initialize the database schema.
// WARNING: This script will execute the SQL queries in data_table_schema.sql.
// It is intended for one-time setup.
// For security, DELETE THIS FILE from your server after you have successfully run it.

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Database Installation Script</h1>";

// --- 1. Load Configuration ---
$config_path = __DIR__ . '/config.php';
if (!file_exists($config_path)) {
    die("<p style='color:red;'><b>Error:</b> config.php not found. Please ensure it exists in the same directory.</p>");
}
require_once $config_path;
echo "<p>Configuration file loaded.</p>";

// --- 2. Connect to the Database ---
try {
    // Connect without specifying a database name first, to ensure we can connect to the server
    $pdo = new PDO("mysql:host=$db_host;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // The SQL file should handle database creation/selection, but we check connection here.
    echo "<p style='color:green;'><b>Success:</b> Connected to MySQL server successfully.</p>";

} catch (PDOException $e) {
    die("<p style='color:red;'><b>Error:</b> Could not connect to the MySQL server. Please check your credentials in config.php. <br>Details: " . $e->getMessage() . "</p>");
}

// --- 3. Read and Execute SQL Schema File ---
$sql_file_path = __DIR__ . '/data_table_schema.sql';
if (!file_exists($sql_file_path)) {
    die("<p style='color:red;'><b>Error:</b> data_table_schema.sql not found.</p>");
}

try {
    echo "<p>Reading SQL schema from 'data_table_schema.sql'...</p>";
    $sql = file_get_contents($sql_file_path);

    // Use PDO's exec() method to execute the multi-query SQL string.
    // NOTE: This is suitable for setup scripts but can be risky if the SQL source is not trusted.
    // Since we are reading our own file, it is safe here.
    $pdo->exec("USE `$db_name`;"); // Ensure we are using the correct database specified in config
    $pdo->exec($sql);

    echo "<p style='color:green;'><b>Success:</b> Database schema imported successfully!</p>";
    echo "<h2>Installation Complete</h2>";
    echo "<p style='color:orange; font-weight:bold;'>IMPORTANT: For security reasons, please delete this 'install.php' file from your server now.</p>";

} catch (PDOException $e) {
    die("<p style='color:red;'><b>Error:</b> Failed to execute the SQL script. <br>Details: " . $e->getMessage() . "</p>");
}

?>
