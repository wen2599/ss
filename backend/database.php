<?php
// backend/database.php

require_once 'config.php';

class Database {
    private static $pdo = null;

    // 获取数据库连接（单例模式）
    public static function getConnection() {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST');
            $db   = getenv('DB_NAME');
            $user = getenv('DB_USER');
            $pass = getenv('DB_PASS');
            $port = getenv('DB_PORT');
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (\PDOException $e) {
                // 记录错误日志，而不是在生产环境中暴露错误信息
                error_log("Database Connection Error: " . $e->getMessage());
                // 抛出通用错误，避免泄露敏感信息
                throw new \PDOException("Could not connect to the database.", (int)$e->getCode());
            }
        }
        return self::$pdo;
    }

    // 保存新的开奖号码
    public static function saveLotteryNumber($number) {
        if (empty($number)) {
            return false;
        }
        $sql = "INSERT INTO lottery_numbers (number) VALUES (?)";
        try {
            $stmt = self::getConnection()->prepare($sql);
            return $stmt->execute([$number]);
        } catch (\PDOException $e) {
            error_log("Error saving lottery number: " . $e->getMessage());
            return false;
        }
    }

    // 获取最新的开奖号码
    public static function getLatestLotteryNumber() {
        $sql = "SELECT number, draw_time FROM lottery_numbers ORDER BY id DESC LIMIT 1";
        try {
            $stmt = self::getConnection()->query($sql);
            $result = $stmt->fetch();
            return $result ? $result : null;
        } catch (\PDOException $e) {
            error_log("Error fetching latest lottery number: " . $e->getMessage());
            return null;
        }
    }
}
?>