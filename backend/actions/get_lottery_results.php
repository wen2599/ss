<?php
// Action: Get all lottery results

try {
    // This query ensures we get exactly one, the most recent, result for each of the three lottery types.
    $sql = "
        (SELECT * FROM lottery_results WHERE lottery_name LIKE '%香港%' ORDER BY parsed_at DESC, id DESC LIMIT 1)
        UNION
        (SELECT * FROM lottery_results WHERE lottery_name LIKE '%新澳门%' ORDER BY parsed_at DESC, id DESC LIMIT 1)
        UNION
        (SELECT * FROM lottery_results WHERE lottery_name LIKE '%老澳%' ORDER BY parsed_at DESC, id DESC LIMIT 1)
    ";
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
