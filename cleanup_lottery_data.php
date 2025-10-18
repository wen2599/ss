<?php
// backend/cleanup_lottery_data.php

require_once __DIR__ . '/backend/db_operations.php';

echo "Starting lottery data cleanup...\n";

$pdo = get_db_connection();

if (is_array($pdo) && isset($pdo['db_error'])) {
    echo "Error: Could not connect to the database. " . $pdo['db_error'] . "\n";
    exit(1);
}

try {
    // 1. Delete incorrectly named results
    $sql_delete_wrong_name = "DELETE FROM lottery_results WHERE lottery_type = '澳门六合彩'";
    $stmt_delete_wrong_name = $pdo->prepare($sql_delete_wrong_name);
    $stmt_delete_wrong_name->execute();
    $deleted_wrong_name_count = $stmt_delete_wrong_name->rowCount();
    echo "Deleted {$deleted_wrong_name_count} records with incorrect name '澳门六合彩'.\n";

    // 2. Remove duplicate results, keeping the latest one
    $sql_delete_duplicates = "
        DELETE r1 FROM lottery_results r1
        INNER JOIN lottery_results r2
        WHERE
            r1.id < r2.id AND
            r1.lottery_type = r2.lottery_type AND
            r1.issue_number = r2.issue_number;
    ";
    $stmt_delete_duplicates = $pdo->prepare($sql_delete_duplicates);
    $stmt_delete_duplicates->execute();
    $deleted_duplicates_count = $stmt_delete_duplicates->rowCount();
    echo "Deleted {$deleted_duplicates_count} duplicate records.\n";

    echo "Cleanup complete.\n";

} catch (PDOException $e) {
    echo "An error occurred during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}
?>