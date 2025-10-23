<?php
declare(strict_types=1);

// Assumes jsonResponse and jsonError functions are available from index.php
// Assumes getDbConnection() is available from index.php

$pdo = getDbConnection();

// 1. Basic Validation
if (!isset($_GET['email']) || empty($_GET['email'])) {
    jsonError(400, '邮箱参数不能为空。');
}

$email = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    jsonError(400, '无效的邮箱格式。');
}

// 2. Check for existing user
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $isRegistered = (bool) $stmt->fetch();
} catch (PDOException $e) {
    error_log('Database error checking if user is registered: ' . $e->getMessage());
    jsonError(500, '查询失败，请稍后再试。');
}

// 3. Return response
jsonResponse(200, [
    'status' => 'success',
    'data' => ['isRegistered' => $isRegistered]
]);
