<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    echo "Reading .env file...\n";
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    print_r($lines);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        putenv(sprintf('%s=%s', $name, $value));
    }
}

$dbHost = getenv('DB_HOST');
if ($dbHost === 'localhost') {
    $dbHost = '127.0.0.1';
}
$dbPort = getenv('DB_PORT');
$dbName = getenv('DB_DATABASE');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASSWORD');

if (!$dbHost || !$dbPort || !$dbName || !$dbUser || !$dbPass) {
    die("Error: Database environment variables are not set. Please check your .env file.");
}

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    echo "Database connection successful.\n";

    $stmt = $pdo->prepare(
        "INSERT INTO emails (user_id, sender, recipient, subject, html_content)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        1, // Assuming user_id 1 exists for testing
        'test@example.com',
        'test@example.com',
        'Test Bill',
        '<h1>This is a test bill</h1>'
    ]);
    echo "Test email added successfully.";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>