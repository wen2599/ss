<?php

class User {

    /**
     * Deletes a user from the database by their Telegram ID.
     */
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

    /**
     * Lists all users from the database.
     */
    public static function listUsersFromDB(PDO $pdo) {
        try {
            $stmt = $pdo->query("SELECT id, telegram_id, username, status FROM users ORDER BY created_at ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($users)) {
                return "数据库中没有用户。";
            }
            $userList = "👤 *所有用户列表:*\n---------------------\n";
            foreach ($users as $index => $user) {
                $username = !empty($user['username']) ? htmlspecialchars($user['username']) : 'N/A';
                $status_icon = '';
                switch ($user['status']) {
                    case 'approved': $status_icon = '✅'; break;
                    case 'pending':  $status_icon = '⏳'; break;
                    case 'denied':   $status_icon = '❌'; break;
                }
                $userList .= ($index + 1) . ". *" . $username . "*\n"
                          . "   DB ID: `" . $user['id'] . "`\n"
                          . "   TG ID: `" . ($user['telegram_id'] ?? 'N/A') . "`\n"
                          . "   状态: " . $status_icon . " `" . htmlspecialchars($user['status']) . "`\n";
            }
            return $userList;
        } catch (PDOException $e) {
            error_log("Error listing users: " . $e->getMessage());
            return "获取用户列表时出错。";
        }
    }

    /**
     * Updates a user's status by their ID (can be database ID or Telegram ID).
     */
    public static function updateUserStatusById(PDO $pdo, $id, $id_column, $status) {
        if ($id_column !== 'id' && $id_column !== 'telegram_id') {
            return false; // Invalid column
        }
        try {
            $sql = "UPDATE users SET status = :status WHERE {$id_column} = :id";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([':status' => $status, ':id' => $id]);
        } catch (PDOException $e) {
            error_log("Error updating user status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a user by their ID (can be database ID or Telegram ID).
     */
    public static function getUserById(PDO $pdo, $id, $id_column) {
         if ($id_column !== 'id' && $id_column !== 'telegram_id') {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE {$id_column} = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching user: " . $e->getMessage());
            return null;
        }
    }
}
?>