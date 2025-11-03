<?php
// backend/handlers/register.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required.']);
    exit;
}

$email = $data['email'];
$password = $data['password'];
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$conn = getDbConnection();

try {
    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $password_hash);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['success' => 'User registered successfully.']);
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) { // Duplicate entry
        http_response_code(409);
        echo json_encode(['error' => 'Email already exists.']);
    } else {
        http_response_code(500);
        error_log("Registration Error: " . $e->getMessage());
        echo json_encode(['error' => 'Internal Server Error']);
    }
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>
