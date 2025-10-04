<?php
require_once __DIR__ . '/../init.php';

header("Content-Type: application/json");

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

$username = trim($input['username']);
$password = $input['password'];

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password cannot be empty.']);
    exit;
}

$users_file = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($users_file), true);

foreach ($users as $user) {
    if ($user['username'] === $username) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already exists.']);
        exit;
    }
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$users[] = [
    'username' => $username,
    'password' => $hashed_password,
];

file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT));

echo json_encode(['success' => 'User registered successfully.']);
?>