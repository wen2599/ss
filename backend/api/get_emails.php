<?php
require_once __DIR__ . '/../src/config.php'; // Includes session_start()

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    Response::json(['error' => 'Authentication required'], 401);
    exit;
}

// If authenticated, proceed to fetch emails for the logged-in user.
$user_id = $_SESSION['user_id'];

$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT id, subject, sender, received_at FROM emails WHERE user_id = ? ORDER BY received_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$emails = [];
while ($row = $result->fetch_assoc()) {
    $emails[] = $row;
}

$stmt->close();
$conn->close();

Response::json($emails);
