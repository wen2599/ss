<?php
// A simple setup script to create the database schema from database_schema.sql
// IMPORTANT: DELETE THIS FILE and database_schema.sql AFTER SETUP!

require __DIR__ . '/api/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- Database Connection ---
$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_DATABASE'];
$dbUser = $_ENV['DB_USERNAME'];
$dbPass = $_ENV['DB_PASSWORD'];

try {
    // Connect to MySQL server without specifying a database initially
    $pdo = new PDO("mysql:host={$dbHost}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Successfully connected to MySQL server.<br>";

    // Read the SQL file
    $sqlFile = __DIR__ . '/database_schema.sql';
    if (!file_exists($sqlFile)) {
        die("ERROR: database_schema.sql not found!");
    }
    $sql = file_get_contents($sqlFile);

    // Execute the SQL commands
    $pdo->exec($sql);

    echo "Database and tables created successfully from database_schema.sql.<br>";
    echo "<strong style='color:red;'>IMPORTANT: Please delete setup.php and database_schema.sql files now!</strong>";

} catch (PDOException $e) {
    // Attempt to provide a more helpful error message
    echo "ERROR: Could not connect to the database or execute setup.<br>";
    echo "<strong>Error Message:</strong> " . $e->getMessage() . "<br><br>";
    echo "<strong>Please check the following:</strong><br>";
    echo "<ul>";
    echo "<li>Is the database server running at '{$dbHost}'?</li>";
    echo "<li>Have you created the database '{$dbName}'? The script tries to, but might not have permission.</li>";
    echo "<li>Are the database credentials in your .env file correct (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)?</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "An unexpected error occurred: " . $e->getMessage();
}
