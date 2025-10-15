<?php
require_once __DIR__ . '/api_header.php';

try {
    $pdo = get_db_connection();

    // Fetch the most recent lottery result
    $stmt = $pdo->query(
        "SELECT issue_number, winning_numbers, drawing_date
         FROM lottery_results
         ORDER BY id DESC
         LIMIT 1"
    );

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Explode the numbers string into an array for easier frontend consumption
        $result['winning_numbers'] = explode(',', $result['winning_numbers']);
        echo json_encode(['status' => 'success', 'data' => $result]);
    } else {
        // No results found, return an empty success response
        echo json_encode(['status' => 'success', 'data' => null]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error while fetching lottery results.']);
}
?>