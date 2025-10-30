<?php
// 文件名: login.php
// 路径: backend/api/auth/login.php
ini_set('display_errors', 1); error_reporting(E_ALL);

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/helpers.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (empty($email) || empty($password)) {
    json_response(['message' => 'Email and password are required.'], 400);
}

try {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $token = generate_jwt($user['id'], $user['email']);
        
        // 返回不包含密码哈希的用户信息
        unset($user['password_hash']);
        
        json_response([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $user
        ], 200);
    } else {
        json_response(['message' => 'Invalid credentials.'], 401);
    }

} catch (PDOException $e) {
    error_log("Login API Error: " . $e->getMessage());
    json_response(['message' => 'Database error during login.'], 500);
}