<?php
require_once __DIR__ . '/db.php';

// Get the email from the query string
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email)) {
    json_response(['message' => 'Email is required.'], false, 400);
    return;
}

// Prepare and execute the query
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

// Check if user exists
$is_registered = $stmt->num_rows > 0;

$stmt->close();
$conn->close();

// Return the response
json_response(['is_registered' => $is_registered]);

?>
