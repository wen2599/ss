<?php
/**
 * db_operations.php
 * This file contains all database-related functions.
 * It relies on bootstrap.php to load the environment and provide the get_db_connection function.
 */

/**
 * Establishes and returns a singleton PDO database connection.
 * @throws PDOException if the connection fails or environment variables are not set.
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $dbname = getenv('DB_DATABASE');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');

        if (empty($host) || empty($port) || empty($dbname) || empty($user)) {
            $error_msg = "Database connection error: Required environment variables are not set. Check .env file.";
            error_log($error_msg);
            throw new PDOException($error_msg);
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        // PDO constructor will throw a PDOException on connection failure
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
    return $pdo;
}

/**
 * Retrieves all users from the database.
 */
function getAllUsers() {
    try {
        $pdo = get_db_connection();
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
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error in deleteUserByEmail for {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * Stores lottery results into the lottery_results table.
 */
function storeLotteryResult($lotteryType, $issueNumber, $winningNumbers, $zodiacSigns, $colors, $drawingDate) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT id FROM lottery_results WHERE lottery_type = ? AND issue_number = ?");
        $stmt->execute([$lotteryType, $issueNumber]);
        if ($stmt->fetch()) {
            error_log("Lottery result for type '{$lotteryType}' and issue '{$issueNumber}' already exists. Skipping insertion.");
            return true;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$lotteryType, $issueNumber, $winningNumbers, $zodiacSigns, $colors, $drawingDate]);
        error_log("Successfully stored lottery result for {$lotteryType} - {$issueNumber}.");
        return true;
    } catch (PDOException $e) {
        error_log("Error storing lottery result for {$lotteryType} - {$issueNumber}: " . $e->getMessage());
        return false;
    }
}
