<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');

// 1. Authentication Check: Ensure the user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in.']);
    exit();
}

// 2. Input Validation: Check for required POST data.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['setting_name']) || !isset($data['setting_value'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Missing required fields: setting_name and setting_value.']);
    exit();
}

$setting_name = $data['setting_name'];
$setting_value = $data['setting_value'];

// 3. Security: Prevent updating critical or unknown settings via this endpoint.
// For now, we only allow updating the 'gemini_api_key'.
$allowed_settings = ['gemini_api_key'];
if (!in_array($setting_name, $allowed_settings)) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'You are not allowed to update this setting.']);
    exit();
}

// 4. Database Update
try {
    $pdo = get_db_connection();
    $sql = "UPDATE application_settings SET setting_value = :setting_value WHERE setting_name = :setting_name";
    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':setting_value' => $setting_value,
        ':setting_name'  => $setting_name
    ]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200); // OK
        echo json_encode(['success' => true, 'message' => 'Setting updated successfully.']);
    } else {
        // This could happen if the setting_name doesn't exist in the table.
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'error' => 'Setting not found or value is already the same.']);
    }

} catch (PDOException $e) {
    error_log("Setting update error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'A database error occurred while updating the setting.']);
}

?>