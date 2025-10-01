<?php
// A script to set up the database and tables for the application.

require_once __DIR__ . '/config.php';

try {
    // 1. Connect to MySQL without specifying a database
    $pdoc = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdoc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create the database if it doesn't exist
    $pdoc->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$db_name' created or already exists.\n";

    // 3. Connect to the newly created database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. Read the SQL schema from the .sql file
    $sql = file_get_contents(__DIR__ . '/data_table_schema.sql');
    if ($sql === false) {
        throw new Exception("Could not read the data_table_schema.sql file.");
    }

    // 5. Execute the SQL to create the tables
    $pdo->exec($sql);
    echo "Tables created successfully.\n";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}

?>