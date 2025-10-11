<?php
require_once 'db.php';

// Get the raw POST data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Basic validation
if (!$data) {
    json_response(['message' => 'Invalid JSON received.'], false, 400);
    return;
}

$required_fields = ['message_id', 'from', 'to', 'subject', 'text_content', 'html_content'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        json_response(['message' => "Missing required field: {$field}"], false, 400);
        return;
    }
}

// Prepare the SQL statement to prevent SQL injection
$stmt = $conn->prepare(
    "INSERT INTO emails (message_id, `from`, `to`, subject, text_content, html_content) VALUES (?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "ssssss",
    $data['message_id'],
    $data['from'],
    $data['to'],
    $data['subject'],
    $data['text_content'],
    $data['html_content']
);

// Execute the statement and check for errors
if ($stmt->execute()) {
    json_response(['message' => 'Email uploaded successfully.', 'id' => $conn->insert_id]);
} else {
    // Check if it's a duplicate entry error
    if ($conn->errno == 1062) { // 1062 is the error code for duplicate entry
        json_response(['message' => 'Duplicate email entry (based on message_id). Ignored.'], true, 200);
    } else {
        json_response(['message' => 'Error uploading email: ' . $stmt->error], false, 500);
    }
}

$stmt->close();
$conn->close();

?>
