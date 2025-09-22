<?php
// A command-line script to import an .sql file into the database.

// Usage: php import.php <path_to_your_sql_file.sql>

// 1. Include Configuration
require_once __DIR__ . '/config.php';

// 2. Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// 3. Get SQL file path from command line arguments
if ($argc < 2) {
    die("Usage: php " . $argv[0] . " <path_to_sql_file>\n");
}
$sql_file_path = $argv[1];

// 4. Check if the file exists and is readable
if (!is_file($sql_file_path) || !is_readable($sql_file_path)) {
    die("Error: File not found or is not readable: " . $sql_file_path . "\n");
}

// 5. Read the SQL file content
$sql_content = file_get_contents($sql_file_path);
if ($sql_content === false) {
    die("Error: Could not read the contents of the SQL file.\n");
}

echo "Connecting to database...\n";

// 6. Database Connection and Execution
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Executing SQL script from " . basename($sql_file_path) . "...\n";

    // Execute the SQL commands from the file
    $pdo->exec($sql_content);

    echo "Successfully imported SQL file.\n";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
?>
