<?php
// File: backend/initialize_database.php (Final Corrected Version)

// 开启命令行错误显示，以便看到任何问题
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "--- Database Initialization Script ---\n\n";
echo "Step 1: Loading core files...\n";

// 【关键修复】确保所有必要的文件都被加载
// 首先加载配置文件，因为它定义了 config() 函数
require_once __DIR__ . '/config.php';
// 然后加载数据库操作文件，因为它定义了 get_db_connection() 函数
require_once __DIR__ . '/db_operations.php'; 

echo "  [SUCCESS] All core files loaded.\n\n";

try {
    echo "Step 2: Getting database connection...\n";
    // 现在 get_db_connection() 函数肯定是已定义的
    $pdo = get_db_connection();
    echo "  [SUCCESS] Database connection established.\n\n";

    echo "Step 3: Reading schema file...\n";
    $sql_file_path = __DIR__ . '/database_schema.sql';
    if (!file_exists($sql_file_path)) {
        throw new Exception("File not found: {$sql_file_path}");
    }
    $sql = file_get_contents($sql_file_path);
    echo "  [SUCCESS] SQL schema file read.\n\n";

    echo "Step 4: Executing SQL to create tables...\n";
    $pdo->exec($sql);
    echo "  [SUCCESS] All tables created or already exist.\n\n";

} catch (Throwable $e) { // 捕获所有类型的错误
    echo "\n[FAILURE] An error occurred during initialization:\n";
    echo "--------------------------------------------------\n";
    echo "Error Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "--------------------------------------------------\n";
    exit(1);
}

echo "--- Initialization Complete! ---\n";
?>