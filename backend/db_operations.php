<?php
/**
 * db_operations.php
 * This file contains functions for database operations.
 * It relies on a centralized config file to load environment variables and establish a connection.
 */

/**
 * Establishes and returns a singleton PDO database connection.
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        // These variables are expected to be loaded by an external config file (e.g., config.php)
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $dbname = getenv('DB_DATABASE');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');

        // Check if the required environment variables are loaded
        if (empty($host) || empty($port) || empty($dbname) || empty($user)) {
             $error_msg = "Database connection error: Required environment variables are not set. Check DB_HOST, DB_PORT, DB_DATABASE, DB_USER in .env";
             error_log($error_msg);
             return ['db_error' => $error_msg];
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            $error_msg = "Database connection failed: " . $e->getMessage();
            error_log($error_msg);
            return ['db_error' => $error_msg];
        }
    }
    return $pdo;
}


/**
 * Retrieves all users from the database.
 */
function getAllUsers() {
    $pdo = get_db_connection();
    if (is_array($pdo) && isset($pdo['db_error'])) {
        error_log("getAllUsers: Failed to get database connection - " . $pdo['db_error']);
        return [];
    }
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getAllUsers: " . $e->getMessage());
        return [];
    }
}

/**
 * Deletes a user from the database by their email address.
 */
function deleteUserByEmail($email) {
    $pdo = get_db_connection();
    if (is_array($pdo) && isset($pdo['db_error'])) {
        error_log("deleteUserByEmail: Failed to get database connection - " . $pdo['db_error']);
        return false;
    }
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error in deleteUserByEmail for {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * Stores lottery results into the lottery_numbers table.
 */
function storeLotteryResult($lotteryType, $issueNumber, $numbers, $source) {
    $pdo = get_db_connection();
    if (is_array($pdo) && isset($pdo['db_error'])) {
        error_log("storeLotteryResult: Failed to get database connection - " . $pdo['db_error']);
        return false;
    }
    if (!$pdo) {
        error_log("storeLotteryResult: No database connection (returned null unexpectedly).");
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT id FROM lottery_numbers WHERE lottery_type = ? AND issue_number = ?");
        $stmt->execute([$lotteryType, $issueNumber]);
        if ($stmt->fetch()) {
            error_log("Lottery result for type '{$lotteryType}' and issue '{$issueNumber}' already exists. Skipping insertion.");
            return true;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO lottery_numbers (lottery_type, issue_number, numbers, source) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$lotteryType, $issueNumber, $numbers, $source]);
        error_log("Successfully stored lottery result for {$lotteryType} - {$issueNumber}.");
        return true;
    } catch (PDOException $e) {
        error_log("Error storing lottery result for {$lotteryType} - {$issueNumber}: " . $e->getMessage());
        return false;
    }
}
