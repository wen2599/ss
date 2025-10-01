<?php

class Lottery {
    /**
     * Saves a parsed lottery result to the database.
     *
     * @param PDO $pdo The database connection object.
     * @param array $result The parsed result array from LotteryParser.
     * @return string A status message indicating the outcome.
     */
    public static function saveLotteryResultToDB(PDO $pdo, $result) {
        $numbers_str = implode(',', $result['numbers']);
        $sql = "INSERT INTO lottery_results (lottery_name, issue_number, numbers)
                VALUES (:lottery_name, :issue_number, :numbers)
                ON DUPLICATE KEY UPDATE numbers = VALUES(numbers), parsed_at = CURRENT_TIMESTAMP";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':lottery_name' => $result['lottery_name'],
                ':issue_number' => $result['issue_number'],
                ':numbers' => $numbers_str
            ]);
            $rowCount = $stmt->rowCount();
            if ($rowCount === 1) {
                return "新开奖结果已成功存入数据库。";
            } elseif ($rowCount >= 1) {
                return "开奖结果已在数据库中更新。";
            } else {
                return "开奖结果与数据库记录一致，未作更改。";
            }
        } catch (PDOException $e) {
            error_log("Database error saving lottery result: " . $e->getMessage());
            return "保存开奖结果时出错。";
        }
    }
}
?>