<?php
// isolated_db_test.php
// This is a temporary script for testing database connectivity directly.
// It will be deleted after the test is complete.

// --- Credentials ---
$db_host = 'mysql12.serv00.com';
$db_port = '3306';
$db_name = 'm10300_sj';
$db_user = 'm10300_yh';
$db_pass = 'Wenxiu1234*';
// ------------------------------------

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

echo "Attempting to connect to database with pre-filled credentials...\n";
echo "Host: {$db_host}\n";
echo "Database: {$db_name}\n";
echo "User: {$db_user}\n\n";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "SUCCESS: Database connection was successful!\n";

    $stmt = $pdo->query("SELECT VERSION()");
    $version = $stmt->fetchColumn();
    echo "MySQL Server Version: " . $version . "\n";

} catch (PDOException $e) {
    echo "ERROR: Database connection failed.\n";
    echo "Error Message: ". $e->getMessage(). "\n";
}
?>
