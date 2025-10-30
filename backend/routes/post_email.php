<?php
// backend/routes/post_email.php

function handle_post_email($conn, $data) {
    if (!isset($data['sender']) || !isset($data['recipient']) || !isset($data['subject']) || !isset($data['body'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required email fields (sender, recipient, subject, body).']);
        return;
    }

    $sender = $conn->real_escape_string($data['sender']);
    $recipient = $conn->real_escape_string($data['recipient']);
    $subject = $conn->real_escape_string($data['subject']);
    $body = $conn->real_escape_string($data['body']);

    $sql = "INSERT INTO emails (sender, recipient, subject, body) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $sender, $recipient, $subject, $body);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Email saved.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save email: ' . $stmt->error]);
    }
    $stmt->close();
}
?>
