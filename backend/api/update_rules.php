<?php
// backend/api/update_rules.php

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// --- Authentication & Authorization ---
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

// Check if the logged-in user is the super admin
if ($_SESSION['user_id'] != TELEGRAM_SUPER_ADMIN_ID) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
    exit();
}

// --- Main Logic ---
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $raw_data = file_get_contents('php://input');
    $updated_rules = json_decode($raw_data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400); // Bad Request
        $response['message'] = 'Invalid JSON provided.';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE lottery_rules SET rule_value = :rule_value WHERE rule_key = :rule_key");

        foreach ($updated_rules as $key => $value) {
            // We need to re-encode the value part back to a JSON string for storage
            $stmt->execute([
                ':rule_value' => json_encode($value),
                ':rule_key' => $key
            ]);
        }

        $pdo->commit();
        $response = ['success' => true, 'message' => 'Rules updated successfully.'];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['message'] = 'Failed to update rules in the database: ' . $e->getMessage();
        http_response_code(500);
    } catch (Exception $e) {
        $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        http_response_code(500);
    }

} else {
    $response['message'] = 'Only POST requests are accepted.';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
?>
