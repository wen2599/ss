<?php
// --- API: Store Parsed Lottery Numbers ---

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Load Configuration ---
require_once __DIR__ . '/../utils/config_loader.php';

// A helper function for consistent JSON responses
function send_json_response($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// 1. Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, 'Invalid request method. Please use POST.', null, 405);
}

// 2. Get and validate incoming structured data
$lottery_type = isset($_POST['lottery_type']) ? trim($_POST['lottery_type']) : null;
$issue_number = isset($_POST['issue_number']) ? trim($_POST['issue_number']) : null;
$numbers = isset($_POST['numbers']) ? trim($_POST['numbers']) : null;

if (empty($lottery_type) || empty($issue_number) || empty($numbers)) {
    send_json_response(false, 'Incomplete lottery data. All fields are required.', null, 400);
}

// --- Database Connection ---
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

// --- Prepare SQL and Insert Data ---
try {
    // Combine the structured data into a single string for storage
    $full_lottery_string = sprintf(
        "%s - 第 %s 期: %s",
        $lottery_type,
        $issue_number,
        $numbers
    );

    // Use the correct table `lottery_numbers` and columns
    $stmt = $conn->prepare("INSERT INTO lottery_numbers (number, source) VALUES (?, ?)");
    
    if ($stmt === false) {
        throw new Exception('SQL statement preparation failed: ' . $conn->error);
    }
    
    $source = 'telegram'; // The source is always telegram for now
    $stmt->bind_param("ss", $full_lottery_string, $source);
    
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $response_data = [
            'id' => $new_id,
            'number' => $full_lottery_string,
            'source' => $source
        ];
        send_json_response(true, 'Lottery result stored successfully.', $response_data, 201);
    } else {
        throw new Exception('SQL execution failed: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    send_json_response(false, 'An internal error occurred while storing the lottery result.', null, 500);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}

?>
