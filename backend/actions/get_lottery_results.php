<?php
// Action: Get all lottery results

try {
    // The $pdo variable is inherited from index.php
    $sql = "SELECT id, lottery_name, issue_number, numbers, parsed_at FROM lottery_results ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode(['success' => true, 'results' => $results]);

} catch (PDOException $e) {
    error_log("Get Lottery Results DB query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve lottery results.']);
}
?>
