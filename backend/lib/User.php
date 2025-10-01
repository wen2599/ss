<?php

namespace App\\Lib;

use PDO;
use PDOException;
use Monolog\\Logger;
use Monolog\\Handler\\StreamHandler;

class User {

    private static function getLogger(): Logger
    {
        $log = new Logger('user_lib');
        $log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Logger::toMonologLevel($_ENV['LOG_LEVEL'] ?? 'INFO')));
        return $log;
    }

    /**
     * Deletes a user from the database by their Telegram ID.
     */
    public static function deleteUserFromDB(PDO $pdo, $telegram_id): string
    {
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
            self::getLogger()->error("Error deleting user: " . $e->getMessage());
            return "❌ 删除用户时发生数据库错误。";
        }
    }

    /**
     * Lists all users from the database.
     */
    public static function listUsersFromDB(PDO $pdo): string
    {
        try {
            $stmt = $pdo->query("SELECT id, telegram_id, username, status, created_at FROM users ORDER BY created_at ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($users)) {
                return "数据库中没有用户。";
            }
            $userList = "👤 *所有用户列表:*\n---------------------\n";
            foreach ($users as $index => $user) {
                $username = !empty($user['username']) ? htmlspecialchars($user['username']) : 'N/A';
                $status_icon = match ($user['status']) {
                    'approved' => '✅',
                    'pending' => '⏳',
                    'denied' => '❌',
                    default => '❔',
                };
                $userList .= ($index + 1) . ". *" . $username . "*\n"
                          . "   DB ID: `" . $user['id'] . "`\n"
                          . "   TG ID: `" . ($user['telegram_id'] ?? 'N/A') . "`\n"
                          . "   状态: " . $status_icon . " `" . htmlspecialchars($user['status']) . "`\n"
                          . "   注册于: `" . date('Y-m-d', strtotime($user['created_at'])) . "`\n";
            }
            return $userList;
        } catch (PDOException $e) {
            self::getLogger()->error("Error listing users: " . $e->getMessage());
            return "获取用户列表时出错。";
        }
    }

    /**
     * Updates a user's status by their ID (can be database ID or Telegram ID).
     */
    public static function updateUserStatusById(PDO $pdo, $id, string $id_column, string $status): bool
    {
        $allowedColumns = ['id', 'telegram_id'];
        if (!in_array($id_column, $allowedColumns)) {
            return false; // Invalid column
        }

        $allowedStatus = ['pending', 'approved', 'denied'];
        if (!in_array($status, $allowedStatus)) {
            return false; // Invalid status
        }

        try {
            $sql = "UPDATE users SET status = :status WHERE {$id_column} = :id";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([':status' => $status, ':id' => $id]);
        } catch (PDOException $e) {
            self::getLogger()->error("Error updating user status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a user by their ID (can be database ID or Telegram ID).
     */
    public static function getUserById(PDO $pdo, $id, string $id_column): ?array
    {
         $allowedColumns = ['id', 'telegram_id'];
         if (!in_array($id_column, $allowedColumns)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE {$id_column} = :id");
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (PDOException $e) {
            self::getLogger()->error("Error fetching user: " . $e->getMessage());
            return null;
        }
    }
}
