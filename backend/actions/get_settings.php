<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');

// 1. Authentication Check: Ensure the user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

// 2. Database Query
try {
    $pdo = get_db_connection();

    // For security, we only fetch the status of settings, not their actual values if they are sensitive.
    $sql = "SELECT
                setting_name,
                CASE
                    WHEN setting_name = 'gemini_api_key' AND (setting_value IS NOT NULL AND setting_value != '' AND setting_value != 'YOUR_GEMINI_API_KEY')
                    THEN 'SET'
                    ELSE 'NOT_SET'
                END AS value
            FROM application_settings
            WHERE setting_name = 'gemini_api_key'";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $settings = [];
    foreach ($results as $row) {
        $settings[$row['setting_name']] = $row['value'];
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'settings' => $settings]);

} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while fetching settings.']);
}
?>