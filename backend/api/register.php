<?php
// backend/api/register.php

require_once __DIR__ . '/database.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Only POST requests are accepted.';
    echo json_encode($response);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // Check if JSON decoding failed
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON received. Error: ' . json_last_error_msg());
    }

    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    // --- Validation ---
    if (empty($email) || empty($password)) {
        throw new Exception('Validation failed: Email or password was not provided.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Validation failed: Invalid email format.');
    }

    if (strlen($password) < 8) {
        throw new Exception('Validation failed: Password must be at least 8 characters long.');
    }

    $pdo = getDbConnection();

    // --- Check if user already exists ---
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        throw new Exception('An account with this email already exists.', 409); // Use code for status
    }

    // --- Create new user ---
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)");
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => $password_hash
    ]);

    $response = [
        'success' => true,
        'message' => 'User registered successfully.'
    ];
    http_response_code(201); // Created

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Database error: ' . $e->getMessage();
    // In a real application, you would log the full error and not expose it to the user.
} catch (Exception $e) {
    // Set appropriate HTTP status code based on the exception type
    if ($e->getCode() == 409) {
         http_response_code(409);
    } else {
         http_response_code(400); // Bad Request for validation errors
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
