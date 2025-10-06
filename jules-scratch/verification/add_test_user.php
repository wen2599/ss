<?php
// jules-scratch/verification/add_test_user.php
require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../../backend/config.php';

$email = 'testuser@example.com';
$password = 'password123';

$conn = get_db_connection();
if (!$conn) {
    echo "Error: Database connection failed.\n";
    exit(1);
}

// Check if user already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo "User already exists. No action taken.\n";
    $stmt->close();
    $conn->close();
    exit(0);
}
$stmt->close();

// Insert new user
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$stmt_insert = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
if (!$stmt_insert) {
    echo "Error preparing insert statement: " . $conn->error . "\n";
    $conn->close();
    exit(1);
}

$stmt_insert->bind_param("ss", $email, $hashed_password);
if ($stmt_insert->execute()) {
    echo "Success: Test user 'testuser@example.com' with password 'password123' created.\n";
} else {
    echo "Error executing insert statement: " . $stmt_insert->error . "\n";
}

$stmt_insert->close();
$conn->close();
?>