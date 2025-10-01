<?php

class User {

    public static function deleteUserFromDB(PDO $pdo, $telegram_id) {
        if (!is_numeric($telegram_id)) {
            return "Telegram ID 无效，必须是数字。";
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE telegram_id = :telegram_id");
            $stmt->execute([':telegram_id' => $telegram_id]);
            if ($stmt->rowCount() > 0) {
                return "✅ 用户 ID `{$telegram_id}` 已被删除。";
            } else {
                return "⚠️ 未找到用户 ID `{$telegram_id}`。";
            }
        } catch (PDOException $e) {
            error_log("Error deleting user: " . $e->getMessage());
            return "❌ 删除用户时发生数据库错误。";
        }
    }

    public static function listUsersFromDB(PDO $pdo) {
        try {
            $stmt = $pdo->query("SELECT telegram_id, username, status FROM users ORDER BY created_at ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($users)) {
                return "数据库中没有用户。";
            }
            $userList = "👤 *所有用户列表:*\n---------------------\n";
            foreach ($users as $index => $user) {
                $username = !empty($user['username']) ? htmlspecialchars($user['username']) : 'N/A';
                $status_icon = '';
                switch ($user['status']) {
                    case 'approved':
                        $status_icon = '✅';
                        break;
                    case 'pending':
                        $status_icon = '⏳';
                        break;
                    case 'denied':
                        $status_icon = '❌';
                        break;
                }
                $userList .= ($index + 1) . ". *" . $username . "*\n"
                          . "   ID: `" . $user['telegram_id'] . "`\n"
                          . "   状态: " . $status_icon . " `" . htmlspecialchars($user['status']) . "`\n";
            }
            return $userList;
        } catch (PDOException $e) {
            error_log("Error listing users: " . $e->getMessage());
            return "获取用户列表时出错。";
        }
    }

    public static function updateUserStatus(PDO $pdo, $user_id, $status) {
        try {
            $sql = "UPDATE users SET status = :status WHERE telegram_id = :telegram_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':status' => $status, ':telegram_id' => $user_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error updating user status: " . $e->getMessage());
            return false;
        }
    }

    public static function registerUser(PDO $pdo, $user_data, $admin_id) {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT status FROM users WHERE telegram_id = :telegram_id OR email = :email");
        $stmt->execute([':telegram_id' => $user_data['user_id'], ':email' => $user_data['email']]);
        $existing_user = $stmt->fetch();

        if ($existing_user) {
            return ['status' => 'info', 'message' => '您已经是注册用户，或该邮箱已被使用。'];
        }

        // Hash the password
        $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);

        // Add new user as pending
        try {
            $sql = "INSERT INTO users (telegram_id, username, email, password, status) VALUES (:telegram_id, :username, :email, :password, 'pending')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':telegram_id' => $user_data['user_id'],
                ':username' => $user_data['username'],
                ':email' => $user_data['email'],
                ':password' => $hashed_password
            ]);

            // On successful registration, notify the admin
            $notification_text = "新的用户注册请求：\n"
                               . "---------------------\n"
                               . "*用户:* `" . htmlspecialchars($user_data['username']) . "`\n"
                               . "*Email:* `" . htmlspecialchars($user_data['email']) . "`\n"
                               . "*Telegram ID:* `" . $user_data['user_id'] . "`\n"
                               . "---------------------\n"
                               . "请批准或拒绝此请求。";

            $approval_keyboard = json_encode([
                'inline_keyboard' => [[
                    ['text' => '✅ 批准', 'callback_data' => 'approve_' . $user_data['user_id']],
                    ['text' => '❌ 拒绝', 'callback_data' => 'deny_' . $user_data['user_id']]
                ]]
            ]);

            sendMessage($admin_id, $notification_text, $approval_keyboard);

            return ['status' => 'success', 'message' => '您的注册申请已提交，请等待管理员批准。'];
        } catch (PDOException $e) {
            error_log("Error registering user: " . $e->getMessage());
            return ['status' => 'error', 'message' => '注册时出错，请稍后再试。'];
        }
    }
}
?>