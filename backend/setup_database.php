<?php
// setup_database.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config.php';

echo "--> 正在尝试连接数据库...\n";

$conn = get_db_connection();
if (!$conn) {
    echo "!!! 错误: 数据库连接失败! 请仔细检查 .env 文件中的配置。\n";
    exit(1);
}
echo "--> ✅ 成功: 数据库已连接。\n\n";

// --- 从 schema.sql 文件创建数据表 ---
echo "--> 正在从 `backend/sql/schema.sql` 读取数据库结构...\n";
$schema_sql = file_get_contents(__DIR__ . '/sql/schema.sql');
if ($schema_sql === false) {
    echo "!!! 错误: 无法读取 schema.sql 文件。\n";
    exit(1);
}

// 使用 mysqli_multi_query 来执行文件中的所有 SQL 语句
if ($conn->multi_query($schema_sql)) {
    // 必须循环并清除每个查询的结果
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "--> ✅ 成功: 所有数据表已根据 schema.sql 创建或验证。\n\n";
} else {
    echo "!!! 错误: 执行 schema.sql 时出错! 原因: " . $conn->error . "\n\n";
}

$conn->close();
echo "--> 所有数据库操作已完成。\n";

?>
