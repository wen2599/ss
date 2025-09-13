<?php
use PHPUnit\Framework\TestCase;

class SettlementsTest extends TestCase
{
    protected static $db;

    public static function setUpBeforeClass(): void
    {
        // Use an in-memory database for testing
        self::$db = new PDO('sqlite::memory:');
        self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create the schema
        $schema = file_get_contents(__DIR__ . '/../api/schema.sql');
        self::$db->exec($schema);
    }

    public function testSettleDraw()
    {
        // 1. Create a user, a draw, and a winning bet
        $stmt = self::$db->prepare("INSERT INTO users (display_id, phone_number, password_hash, points) VALUES (?, ?, ?, ?)");
        $stmt->execute(['test_user', '1234567890', 'hash', 1000]);
        $user_id = self::$db->lastInsertId();

        $stmt = self::$db->prepare("INSERT INTO lottery_draws (draw_number, draw_date, status) VALUES (?, ?, ?)");
        $stmt->execute(['2025001', '2025-01-01', 'closed']);
        $draw_id = self::$db->lastInsertId();

        $winning_numbers = [1, 2, 3, 4, 5, 6];
        $bet_amount = 10;
        $stmt = self::$db->prepare("INSERT INTO bets (user_id, draw_id, bet_type, bet_numbers, bet_amount, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $draw_id, 'single', json_encode($winning_numbers), $bet_amount, 'placed']);

        // 2. Simulate settling the draw
        self::$db->beginTransaction();
        try {
            $stmt = self::$db->prepare("UPDATE lottery_draws SET winning_numbers = ?, status = 'settled' WHERE id = ?");
            $stmt->execute([json_encode($winning_numbers), $draw_id]);

            $prize_amount = 10000;
            $winnings = $bet_amount * $prize_amount;

            $stmt = self::$db->prepare("UPDATE bets SET status = 'won', winnings = ? WHERE draw_id = ?");
            $stmt->execute([$winnings, $draw_id]);

            $stmt = self::$db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            $stmt->execute([$winnings, $user_id]);

            self::$db->commit();
        } catch (Exception $e) {
            self::$db->rollBack();
            $this->fail("Draw settlement transaction failed: " . $e->getMessage());
        }

        // 3. Check results
        $stmt = self::$db->prepare("SELECT * FROM lottery_draws WHERE id = ?");
        $stmt->execute([$draw_id]);
        $draw = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('settled', $draw['status']);

        $stmt = self::$db->prepare("SELECT * FROM bets WHERE draw_id = ?");
        $stmt->execute([$draw_id]);
        $bet = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('won', $bet['status']);
        $this->assertEquals($winnings, $bet['winnings']);

        $stmt = self::$db->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $points = $stmt->fetchColumn();
        $this->assertEquals(1000 + $winnings, $points);
    }
}
