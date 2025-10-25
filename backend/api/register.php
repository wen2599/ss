
<?php

// --- CORS Configuration ---
// This section handles Cross-Origin Resource Sharing (CORS) preflight requests and sets headers.

// Allow requests from your specific frontend origin.
// IMPORTANT: Replace * with your actual frontend domain in production, e.g., 'https://ss.wenxiuxiu.eu.org'
$allowed_origin = 'https://ss.wenxiuxiu.eu.org';

header("Access-Control-Allow-Origin: " . $allowed_origin);
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle the browser's preflight 'OPTIONS' request.
// This is crucial for CORS to work correctly with methods like POST.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Respond with a 204 No Content status, indicating success and that no further action is needed.
    http_response_code(204);
    // Stop script execution, as this was just a preflight check.
    exit;
}

require_once __DIR__ . '/../bootstrap.php';

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['email']) && isset($data['password'])) {
    $email = $data['email'];
    $password = password_hash($data['password'], PASSWORD_BCRYPT);

    global $db_connection;
    $stmt = $db_connection->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $password);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "User created successfully"]);
    } else {
        http_response_code(409); // Conflict, user likely already exists
        echo json_encode(["message" => "User with this email already exists"]);
    }
    $stmt->close();
    $db_connection->close();
} else {
    http_response_code(400);
    echo json_encode(["message" => "Email and password are required"]);
}
