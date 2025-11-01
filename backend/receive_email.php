<?php
function processEmail() {
    $from = $_POST['from'];
    $to = $_POST['to'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];

    $conn = getDbConnection();

    // Find the user by email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $user_id = $user['id'];
        $stmt = $conn->prepare("INSERT INTO emails (user_id, from_address, to_address, subject, body_text) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $from, $to, $subject, $body);
        $stmt->execute();
    }

    echo json_encode(['success' => true]);
}
