<?php
// database.php

require_once 'config.php';

class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            $host = get_env_variable('DB_HOST');
            $db   = get_env_variable('DB_NAME');
            $user = get_env_variable('DB_USER');
            $pass = get_env_variable('DB_PASS');
            $port = get_env_variable('DB_PORT', '3306');
            $charset = 'utf8mb4';

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

    // --- Lottery ---
    public static function saveLotteryResult($lottery_type, $issue_number, $numbers) {
        if (empty($numbers)) {
            return false;
        }
        $sql = "INSERT INTO lottery_results (lottery_type, issue_number, numbers) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE numbers = VALUES(numbers)";
        try {
            $stmt = self::getConnection()->prepare($sql);
            return $stmt->execute([$lottery_type, $issue_number, $numbers]);
        } catch (\PDOException $e) {
            error_log("Error saving lottery result: " . $e->getMessage());
            return false;
        }
    }

    public static function getLatestLotteryResult() {
        $sql = "SELECT lottery_type, issue_number, numbers, draw_time FROM lottery_results ORDER BY id DESC LIMIT 1";
        try {
            $stmt = self::getConnection()->query($sql);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            error_log("Error fetching latest lottery result: " . $e->getMessage());
            return null;
        }
    }

    // --- Users ---
    public static function findUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (\PDOException $e) {
            error_log("Error finding user by email: " . $e->getMessage());
            return false;
        }
    }

    public static function createUser($email, $password_hash) {
        $sql = "INSERT INTO users (email, password) VALUES (?, ?)";
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute([$email, $password_hash]);
            return self::getConnection()->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }

    public static function updateUserToken($user_id, $token, $expires_at) {
        $sql = "UPDATE users SET auth_token = ?, token_expires_at = ? WHERE id = ?";
        try {
            $stmt = self::getConnection()->prepare($sql);
            return $stmt->execute([$token, $expires_at, $user_id]);
        } catch (\PDOException $e) {
            error_log("Error updating user token: " . $e->getMessage());
            return false;
        }
    }

    // --- Emails ---
    public static function saveEmail($user_id, $from, $to, $subject, $body) {
        $sql = "INSERT INTO emails (user_id, from_email, to_email, subject, body) VALUES (?, ?, ?, ?, ?)";
        try {
            $stmt = self::getConnection()->prepare($sql);
            return $stmt->execute([$user_id, $from, $to, $subject, $body]);
        } catch (\PDOException $e) {
            error_log("Error saving email: " . $e->getMessage());
            return false;
        }
    }
}
?>