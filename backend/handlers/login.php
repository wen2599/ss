<?php
// backend/handlers/login.php
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

$conn = getDbConnection();

try {
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Generate a token
            $token = bin2hex(random_bytes(32));
            $user_id = $user['id'];

            $insertStmt = $conn->prepare("INSERT INTO tokens (user_id, token) VALUES (?, ?)");
            $insertStmt->bind_param("is", $user_id, $token);
            $insertStmt->execute();

            echo json_encode(['token' => $token]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Login Error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal Server Error']);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($insertStmt)) $insertStmt->close();
    if (isset($conn)) $conn->close();
}
?>