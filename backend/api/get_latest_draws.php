<?php
// backend/api/get_latest_draws.php
// Public endpoint to fetch the latest draw results for each lottery type.

header('Content-Type: application/json');
require_once __DIR__ . '/database.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = getDbConnection();

        // This query fetches the latest draw for each of the three specified lotteries.
        // It's structured as a UNION of three separate queries for clarity and compatibility.
        $sql = "
            (SELECT * FROM lottery_draws WHERE lottery_name = '新澳门六合彩' ORDER BY issue_number DESC, created_at DESC LIMIT 1)
            UNION ALL
            (SELECT * FROM lottery_draws WHERE lottery_name = '香港六合彩' ORDER BY issue_number DESC, created_at DESC LIMIT 1)
            UNION ALL
            (SELECT * FROM lottery_draws WHERE lottery_name = '老澳21.30' ORDER BY issue_number DESC, created_at DESC LIMIT 1)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $draws_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Re-key the array by lottery name for easier use on the frontend
        $draws_formatted = [];
        foreach ($draws_raw as $draw) {
            if (isset($draw['winning_numbers'])) {
                // Decode the JSON string into an object
                $draw['winning_numbers'] = json_decode($draw['winning_numbers']);
            }
            $draws_formatted[$draw['lottery_name']] = $draw;
        }

        $response = [
            'success' => true,
            'data' => $draws_formatted
        ];

    } catch (PDOException $e) {
        $response['message'] = 'Failed to retrieve draw results: ' . $e->getMessage();
        http_response_code(500);
    }
} else {
    $response['message'] = 'Only GET requests are accepted.';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
?>
