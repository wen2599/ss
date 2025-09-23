<?php
// Action: Register a new user

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed for registration.']);
    exit();
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

// --- AGGRESSIVE DEBUGGING ---
// We will stop the script here to see exactly what data is being received.
header('Content-Type: text/plain; charset=utf-8'); // Ensure plain text for readability
echo "--- DEBUG OUTPUT ---";
echo "\n\n";
echo "Raw Input Stream:\n";
print_r($input);
echo "\n\n";
echo "json_decode() Result:\n";
print_r($data);
echo "\n\n";
echo "Email from decoded data:\n";
print_r($data['email'] ?? 'NOT SET');
echo "\n\n";
echo "--- END DEBUG ---";
exit();
// --- END AGGRESSIVE DEBUGGING ---


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

try {
    // The $pdo variable is inherited from index.php
    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (:email, :password)");
    $stmt->execute([':email' => $email, ':password' => $password_hash]);

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
