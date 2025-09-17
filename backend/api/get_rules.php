<?php
// backend/api/get_rules.php

// --- Public endpoint to fetch lottery rules ---

header('Content-Type: application/json');
require_once __DIR__ . '/database.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT rule_key, rule_value FROM lottery_rules");
        $stmt->execute();
        $rules_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transform the array into a key-value object for easier use on the frontend
        $rules_formatted = [];
        foreach ($rules_raw as $row) {
            // json_decode the value since it's stored as a JSON string
            $rules_formatted[$row['rule_key']] = json_decode($row['rule_value']);
        }

        $response = [
            'success' => true,
            'data' => $rules_formatted
        ];

    } catch (PDOException $e) {
        $response['message'] = 'Failed to retrieve rules from the database: ' . $e->getMessage();
        http_response_code(500);
    }
} else {
    $response['message'] = 'Only GET requests are accepted.';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
?>
