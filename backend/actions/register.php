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

// Check if the email is pre-authorized
$stmt = $pdo->prepare("SELECT id FROM allowed_emails WHERE email = :email");
$stmt->execute([':email' => $email]);
if (!$stmt->fetch()) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => '请联系管理员授权邮箱']);
    exit();
}

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

// Use a transaction to ensure atomicity
$pdo->beginTransaction();

try {
    // Add new user as pending
    $sql_insert_user = "INSERT INTO users (email, password, username, status) VALUES (:email, :password, :username, 'pending')";
    $stmt_insert_user = $pdo->prepare($sql_insert_user);
    $stmt_insert_user->execute([
        ':email' => $email,
        ':password' => $hashed_password,
        ':username' => $username
    ]);
    $new_user_db_id = $pdo->lastInsertId();

    // Remove the email from the allowed list
    $sql_delete_email = "DELETE FROM allowed_emails WHERE email = :email";
    $stmt_delete_email = $pdo->prepare($sql_delete_email);
    $stmt_delete_email->execute([':email' => $email]);

    // Commit the transaction
    $pdo->commit();

    // On successful registration, notify the admin via Telegram.
    // This is in a separate try-catch so that a notification failure
    // does not prevent the user from receiving a success response.
    try {
        $notification_text = "新用户注册成功 (来自网站)：\n"
                           . "---------------------\n"
                           . "*用户:* `" . htmlspecialchars($username) . "`\n"
                           . "*Email:* `" . htmlspecialchars($email) . "`\n"
                           . "---------------------\n"
                           . "用户状态自动设为 'pending'。";

        // No keyboard is sent as approval is no longer done via the bot.
        Telegram::sendMessage($admin_id, $notification_text);
    } catch (Throwable $e) {
        // Log the error, but don't prevent the user from getting a success response.
        error_log("Failed to send Telegram notification for new user " . $new_user_db_id . ": " . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => '您的注册申请已提交，请等待管理员批准。']);

} catch (PDOException $e) {
    // If something went wrong, roll back the transaction
    $pdo->rollBack();
    error_log("Error registering web user: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '注册时发生数据库错误。']);
}
?>