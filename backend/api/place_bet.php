<?php
// backend/api/place_bet.php
require_once 'config.php';
require_once 'db_connect.php';
header('Content-Type: application/json');

// Start session to access logged-in user data
session_set_cookie_params(['samesite' => 'None', 'secure' => true]);
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'You must be logged in to place a bet.']);
    exit;
}
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON.']);
    exit;
}

$numbers = $data['numbers'] ?? null;
$lottery_type = $data['lottery_type'] ?? null;

// --- Validation ---
if (!$numbers || !is_array($numbers) || count($numbers) !== 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You must select exactly 6 numbers.']);
    exit;
}
foreach ($numbers as $num) {
    if (!is_int($num) || $num < 1 || $num > 49) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid number selection. Numbers must be between 1 and 49.']);
        exit;
    }
}

if (empty($lottery_type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lottery type is required.']);
    exit;
}

$conn = db_connect();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // Get the latest draw period for the specified lottery type
    $stmt = $conn->prepare("SELECT period FROM draws WHERE lottery_type = ? ORDER BY draw_time DESC LIMIT 1");
    $stmt->bind_param("s", $lottery_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $latest_draw = $result->fetch_assoc();

    if (!$latest_draw) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No active draw period found for this lottery type.']);
        $conn->close();
        exit;
    }
    $period = $latest_draw['period'];

    // Use the logged-in user's ID
    $numbers_str = implode(',', $numbers);

    $stmt = $conn->prepare("INSERT INTO bets (user_id, numbers, period, lottery_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $numbers_str, $period, $lottery_type);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Bet placed successfully!']);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
}

$conn->close();
?>
