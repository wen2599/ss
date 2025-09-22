<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed.']);
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

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
        echo json_encode(['success' => false, 'error' => 'A server error occurred.']);
    }
    exit();
}
?>
