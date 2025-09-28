<?php
// A simple, one-time migration script to update the database schema.
// This should be run from the command line: `php migrate.php`

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config.php';

try {
    echo "🚀 Starting Database Migration...\n\n";

    // 1. Connect to the database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Successfully connected to database '$db_name'.\n";

    // 2. Check for and add the 'confidence' column
    $stmt = $pdo->query("SHOW COLUMNS FROM `bills` LIKE 'confidence'");
    if ($stmt->rowCount() == 0) {
        echo "   - Column 'confidence' not found. Adding it now...\n";
        $pdo->exec("ALTER TABLE `bills` ADD `confidence` DECIMAL(5, 4) NULL COMMENT 'Confidence score of the parsing, from 0.0 to 1.0' AFTER `status`;");
        echo "   ✅ Column 'confidence' added successfully.\n";
    } else {
        echo "   - Column 'confidence' already exists. Skipping.\n";
    }

    // 3. Check for and add the 'unparsed_text' column
    $stmt = $pdo->query("SHOW COLUMNS FROM `bills` LIKE 'unparsed_text'");
    if ($stmt->rowCount() == 0) {
        echo "   - Column 'unparsed_text' not found. Adding it now...\n";
        $pdo->exec("ALTER TABLE `bills` ADD `unparsed_text` TEXT NULL COMMENT 'Any text that was not successfully parsed' AFTER `confidence`;");
        echo "   ✅ Column 'unparsed_text' added successfully.\n";
    } else {
        echo "   - Column 'unparsed_text' already exists. Skipping.\n";
    }

    echo "\n🎉 Database migration complete!\n";

} catch (PDOException $e) {
    http_response_code(500);
    die("❌ DATABASE ERROR: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    http_response_code(500);
    die("❌ GENERAL ERROR: " . $e->getMessage() . "\n");
}
?>