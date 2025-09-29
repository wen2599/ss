<?php
// Action: Register a new user

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed for registration.']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing email.']);
    exit();
}
if (!isset($data['password']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password is required.']);
    exit();
}

$email = $data['email'];
$password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
$winning_rate = $data['winning_rate'] ?? 45; // Default to 45 if not provided

// Validate the winning rate to ensure it's one of the allowed values
if (!in_array($winning_rate, [45, 47])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid winning rate. Must be 45 or 47.']);
    exit();
}

try {
    // The $pdo variable is inherited from index.php
    $stmt = $pdo->prepare("INSERT INTO users (email, password, winning_rate) VALUES (:email, :password, :winning_rate)");
    $stmt->execute([
        ':email' => $email,
        ':password' => $password_hash,
        ':winning_rate' => $winning_rate
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'User registered successfully.']);

} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1062) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'User with this email already exists.']);
    } else {
        error_log("Registration DB error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'A server error occurred during registration.']);
    }
    exit();
}
?>
