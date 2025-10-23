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
if (strlen($password) < 6) {
    jsonError(400, '密码长度不能少于6位。');
}

// 2. Check for existing user
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        jsonError(409, '该邮箱已被注册。');
    }
} catch (PDOException $e) {
    error_log('Database error checking existing user: ' . $e->getMessage());
    jsonError(500, '注册失败，请稍后再试。');
}

// 3. Create and save the new user
try {
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (:username, :email, :password)');
    $stmt->execute([
        'username' => $email, // Using email as username for now
        'email' => $email,
        'password' => $hashedPassword
    ]);
    $userId = $pdo->lastInsertId();
} catch (PDOException $e) {
    error_log('Database error creating user: ' . $e->getMessage());
    jsonError(500, '注册失败，请稍后再试。');
}

// 4. Start session and log in the new user
$_SESSION['user_id'] = $userId;
$_SESSION['username'] = $email;

// 5. Return success response
jsonResponse(201, [
    'status' => 'success',
    'data' => [
        'message' => '注册成功！',
        'user_id' => $userId
    ]
]);
