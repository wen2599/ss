<?php
// backend/api/get_bets.php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/database.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = getDbConnection();
        $user_id = $_SESSION['user_id'];

        // Fetch bets for the logged-in user
        $stmt = $pdo->prepare("SELECT * FROM bets WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // The bet_data and settlement_data are stored as JSON strings, so we need to decode them
        foreach ($bets as &$bet) {
            if (isset($bet['bet_data'])) {
                $bet['bet_data'] = json_decode($bet['bet_data']);
            }
            if (isset($bet['settlement_data'])) {
                $bet['settlement_data'] = json_decode($bet['settlement_data']);
            }
        }

        $response = [
            'success' => true,
            'data' => $bets
        ];

    } catch (PDOException $e) {
        $response['message'] = 'Failed to retrieve bets from the database.';
        http_response_code(500);
    }
} else {
    $response['message'] = 'Only GET requests are accepted.';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
?>
