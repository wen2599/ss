<?php

namespace App\\Lib;

use PDO;
use PDOException;
use Monolog\\Logger;
use Monolog\\Handler\\StreamHandler;

class Lottery {

    private static function getLogger(): Logger
    {
        $log = new Logger('lottery_lib');
        $log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Logger::toMonologLevel($_ENV['LOG_LEVEL'] ?? 'INFO')));
        return $log;
    }

    /**
     * Saves a parsed lottery result to the database.
     *
     * @param PDO $pdo The database connection object.
     * @param array $result The parsed result array from LotteryParser.
     * @return string A status message indicating the outcome.
     */
    public static function saveLotteryResultToDB(PDO $pdo, array $result): string
    {
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
            
            // In MySQL with ON DUPLICATE KEY UPDATE, rowCount() returns:
            // 0 if no change was made (the record already existed and was identical).
            // 1 if a new row was inserted.
            // 2 if an existing row was updated.
            $rowCount = $stmt->rowCount();
            
            if ($rowCount === 1) {
                return "新开奖结果已成功存入数据库。";
            } elseif ($rowCount === 2) {
                return "开奖结果已在数据库中更新。";
            } else { // rowCount is 0 or another value
                return "开奖结果与数据库记录一致，未作更改。";
            }
        } catch (PDOException $e) {
            self::getLogger()->error("Database error saving lottery result: " . $e->getMessage(), ['result' => $result]);
            return "保存开奖结果时发生数据库错误。";
        }
    }
}
