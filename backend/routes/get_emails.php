<?php
// backend/routes/get_emails.php

function handle_get_emails($conn) {
    $sql = "SELECT id, sender, subject, created_at FROM emails ORDER BY created_at DESC LIMIT 100";
    $result = $conn->query($sql);
    $emails = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $emails[] = $row;
        }
    }
    echo json_encode($emails);
}

function handle_get_email_by_id($conn, $id) {
    $sql = "SELECT * FROM emails WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $email = $result->fetch_assoc();
        echo json_encode($email);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Email not found.']);
    }
    $stmt->close();
}
?>
