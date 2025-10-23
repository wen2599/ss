<?php
declare(strict_types=1);

// Assumes jsonResponse and jsonError functions are available from index.php
// Assumes getDbConnection() is available from index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDbConnection();
$input = json_decode(file_get_contents('php://input'), true);

// 1. Basic Validation
if (!isset($input['email']) || !isset($input['password']) || empty($input['email']) || empty($input['password'])) {
    jsonError(400, '邮箱和密码不能为空。');
}

$email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    jsonError(400, '无效的邮箱格式。');
}

$password = $input['password'];

// 2. Find user by email
try {
    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonError(401, '用户不存在或密码错误。'); // Use a generic message for security
    }
} catch (PDOException $e) {
    error_log('Database error finding user: ' . $e->getMessage());
    jsonError(500, '登录失败，请稍后再试。');
}

// 3. Verify password
if (!password_verify($password, $user['password'])) {
    jsonError(401, '用户不存在或密码错误。'); // Use a generic message for security
}

// 4. Start session and log in user
session_regenerate_id(true); // Prevent session fixation
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

// 5. Return success response
jsonResponse(200, [
    'status' => 'success',
    'data' => [
        'message' => '登录成功！',
        'user_id' => $user['id'],
        'username' => $user['username']
    ]
]);
