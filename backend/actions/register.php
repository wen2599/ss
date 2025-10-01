<?php
// Action: Handle web-based user registration

// This script is included by index.php, so it has access to $pdo and $admin_id.
global $pdo, $admin_id;

// We must require the libraries as they are not loaded by the router.
require_once __DIR__ . '/../lib/Telegram.php';
require_once __DIR__ . '/../lib/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed for registration.']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !isset($data['password']) || strlen($data['password']) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input. A valid email and a password of at least 6 characters are required.']);
    exit();
}

$email = $data['email'];
$password = $data['password'];
$username = $data['username'] ?? 'WebApp User';

// Check if user already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'error' => 'This email address is already registered.']);
    exit();
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Add new user as pending
    $sql = "INSERT INTO users (email, password, username, status) VALUES (:email, :password, :username, 'pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $email,
        ':password' => $hashed_password,
        ':username' => $username
    ]);

    $new_user_db_id = $pdo->lastInsertId();

    // On successful registration, notify the admin via Telegram
    $notification_text = "新的网站用户注册请求：\n"
                       . "---------------------\n"
                       . "*用户:* `" . htmlspecialchars($username) . "`\n"
                       . "*Email:* `" . htmlspecialchars($email) . "`\n"
                       . "*数据库 ID:* `" . $new_user_db_id . "`\n"
                       . "---------------------\n"
                       . "请批准或拒绝此请求。";

    // The callback data for web users must be different to distinguish them.
    // We will use the database ID (`dbid`) for these users.
    $approval_keyboard = json_encode([
        'inline_keyboard' => [[
            ['text' => '✅ 批准', 'callback_data' => 'approve_dbid_' . $new_user_db_id],
            ['text' => '❌ 拒绝', 'callback_data' => 'deny_dbid_' . $new_user_db_id]
        ]]
    ]);

    Telegram::sendMessage($admin_id, $notification_text, $approval_keyboard);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => '您的注册申请已提交，请等待管理员批准。']);

} catch (PDOException $e) {
    error_log("Error registering web user: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '注册时发生数据库错误。']);
}
?>