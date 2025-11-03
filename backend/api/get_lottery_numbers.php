<?php
// --- API: Get All Lottery Numbers ---

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For simplicity, allow all. For production, restrict to frontend URL.
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Load Configuration ---
// This is the critical step to ensure database credentials are available
require_once __DIR__ . '/../utils/config_loader.php';

// A helper function for consistent JSON responses
function send_json_response($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(false, 'Invalid request method. Please use GET.', null, 405);
}

// --- Database Connection ---
// Use environment variables loaded by the config_loader
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

if (!$db_host || !$db_user || !$db_pass || !$db_name) {
    error_log("Database configuration is incomplete. Check .env file.");
    send_json_response(false, 'Server configuration error.', null, 500);
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    send_json_response(false, 'Database service is currently unavailable.', null, 503);
}
$conn->set_charset('utf8mb4');

// --- Query Data ---
try {
    // Query the correct table `lottery_numbers` and order by creation time
    $query = "SELECT id, number, source, received_at FROM lottery_numbers ORDER BY received_at DESC";
    $result = $conn->query($query);

    if ($result === false) {
        throw new Exception('Database query failed: ' . $conn->error);
    }

    $numbers = [];
    while ($row = $result->fetch_assoc()) {
        $numbers[] = $row;
    }

    send_json_response(true, 'Successfully retrieved lottery numbers.', $numbers);

} catch (Exception $e) {
    error_log($e->getMessage());
    // TEMPORARY: Expose detailed error for debugging
    send_json_response(false, 'An error occurred while fetching data: ' . $e->getMessage(), null, 500);
} finally {
    if (isset($result) && $result instanceof mysqli_result) {
        $result->free();
    }
    $conn->close();
}

?>
