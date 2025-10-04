<?php
require_once __DIR__ . '/../init.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Credentials: true");
// This might be needed for local dev across different ports
header("Access-Control-Allow-Origin: http://localhost:5173");

session_start();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

$username = $input['username'];
$password = $input['password'];

$users_file = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($users_file), true);

foreach ($users as $user) {
    if ($user['username'] === $username) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;

            echo json_encode([
                'success' => 'Logged in successfully.',
                'user' => ['username' => $username]
            ]);
            exit;
        }
    }
}

http_response_code(401);
echo json_encode(['error' => 'Invalid username or password.']);
?>