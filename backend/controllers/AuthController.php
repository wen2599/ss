<?php
class AuthController {
    private $pdo;
    public function __construct() { $this->pdo = get_db_connection(); }

    public function register($data) {
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        if (!$email || !$password) { send_json_response(['error' => '邮箱和密码不能为空'], 400); }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) { send_json_response(['error' => '该邮箱已被注册'], 409); }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
        if ($stmt->execute([$email, $hash])) {
            send_json_response(['message' => '注册成功'], 201);
        } else {
            send_json_response(['error' => '注册失败'], 500);
        }
    }

    public function login($data) {
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        if (!$email || !$password) { send_json_response(['error' => '邮箱和密码不能为空'], 400); }

        $stmt = $this->pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $token = Auth::generateToken($user['id']);
            send_json_response(['token' => $token]);
        } else {
            send_json_response(['error' => '邮箱或密码错误'], 401);
        }
    }
}
