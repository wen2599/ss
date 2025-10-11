<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';

header('Content-Type: application/json');

$email_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$response = [
    'success' => true,
    'emails' => []
];

if ($email_id !== null) {
    // --- Fetch a Single Email ---
    $stmt = $conn->prepare("SELECT id, `from`, `to`, subject, html_content, received_at FROM emails WHERE id = ?");
    $stmt->bind_param("i", $email_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($email = $result->fetch_assoc()) {
        $response['emails'][] = $email;
    }

    $stmt->close();

} else {
    // --- Fetch All Emails (summary) ---
    $result = $conn->query("SELECT id, `from`, subject, received_at FROM emails ORDER BY received_at DESC");

    while ($row = $result->fetch_assoc()) {
        $response['emails'][] = $row;
    }
}

$conn->close();

echo json_encode($response);

?>
