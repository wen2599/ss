<?php
// database.php

require_once 'config.php';

class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            // 使用新的辅助函数来获取配置
            $host = get_env_variable('DB_HOST');
            $db   = get_env_variable('DB_NAME');
            $user = get_env_variable('DB_USER');
            $pass = get_env_variable('DB_PASS');
            $port = get_env_variable('DB_PORT', '3306');
            $charset = 'utf8mb4';

            // 增加一个检查，如果关键配置为空则直接报错
            if (empty($host) || empty($db) || empty($user)) {
                 die("FATAL ERROR in database.php: Database configuration is missing. Please check your .env file.");
            }

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (\PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                die("FATAL ERROR: Could not connect to the database. Check connection details and server status.");
            }
        }
        return self::$pdo;
    }

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

    public static function getLatestLotteryNumber() {
        $sql = "SELECT number, draw_time FROM lottery_numbers ORDER BY id DESC LIMIT 1";
        try {
            $stmt = self::getConnection()->query($sql);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            error_log("Error fetching latest lottery number: " . $e->getMessage());
            return null;
        }
    }
}
?>