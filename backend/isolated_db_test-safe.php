<?php
// isolated_db_test.php - SAFE VERSION
// This version has all details pre-filled EXCEPT for the password.

// --- Database Credentials ---
$db_host = 'mysql12.serv00.com';
$db_port = '3306';
$db_name = 'm10300_sj';
$db_user = 'm10300_yh';

// !! IMPORTANT !!
// !! Please manually enter your database password in the line below before running the script. !!
$db_pass = 'Wenxiu1234*'; // <<< 在这里手动输入您的密码

// ------------------------------------

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

echo "Attempting to connect to database...\n";
echo "Host: {$db_host}\n";
echo "Database: {$db_name}\n";
echo "User: {$db_user}\n\n";

try {
    // Check if password is still the placeholder
    if ($db_pass === 'your_database_password_here') {
        throw new Exception("Please edit this file and enter your database password before running.");
    }

    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "SUCCESS: Database connection was successful!\n";

    $stmt = $pdo->query("SELECT VERSION()");
    $version = $stmt->fetchColumn();
    echo "MySQL Server Version: " . $version . "\n";

} catch (Exception $e) { // Changed to catch generic Exception
    echo "ERROR: An error occurred.\n";
    echo "Error Message: " . $e->getMessage() . "\n";
}
?>
