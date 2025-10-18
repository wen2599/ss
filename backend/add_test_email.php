<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

echo "Config loaded.\n";

$pdo = get_db_connection();

echo "Database connection attempted.\n";

if ($pdo) {
    echo "Database connection successful.\n";
} else {
    echo "Database connection failed.\n";
    exit;
}

try {
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
    echo "Error adding test email: " . $e->getMessage();
    error_log("Error adding test email: " . $e->getMessage());
}
echo "Script finished.";
?>