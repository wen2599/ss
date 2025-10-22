<?php
// A simple setup script to create the database schema from database_schema.sql
// IMPORTANT: DELETE THIS FILE and database_schema.sql AFTER SETUP!

// --- Self-contained .env loader ---
function loadEnv($path) {
    if (!file_exists($path)) {
        die("ERROR: .env file not found at '{$path}'. Please copy .env.example to .env and fill in your details.");
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load environment variables from the project root .env file
loadEnv(__DIR__ . '/../.env');

// --- Database Connection ---
$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_DATABASE'];
$dbUser = $_ENV['DB_USER']; // Modified to DB_USER
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
    echo "<li>Are the database credentials in your .env file correct (DB_HOST, DB_DATABASE, DB_USER, DB_PASSWORD)?</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "An unexpected error occurred: " . $e->getMessage();
}
