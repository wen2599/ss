<?php
use PHPUnit\Framework\TestCase;

class BetsTest extends TestCase
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

    public function testPlaceBet()
    {
        // 1. Create a user and a draw
        $stmt = self::$db->prepare("INSERT INTO users (display_id, phone_number, password_hash, points) VALUES (?, ?, ?, ?)");
        $stmt->execute(['test_user', '1234567890', 'hash', 1000]);
        $user_id = self::$db->lastInsertId();

        $stmt = self::$db->prepare("INSERT INTO lottery_draws (draw_number, draw_date, status) VALUES (?, ?, ?)");
        $stmt->execute(['2025001', '2025-01-01', 'open']);
        $draw_id = self::$db->lastInsertId();

        // 2. Simulate placing a bet
        $bet_amount = 100;
        self::$db->beginTransaction();
        try {
            $stmt = self::$db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
            $stmt->execute([$bet_amount, $user_id]);

            $stmt = self::$db->prepare("INSERT INTO bets (user_id, draw_id, bet_type, bet_numbers, bet_amount) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $draw_id, 'single', '[1,2,3,4,5,6]', $bet_amount]);
            self::$db->commit();
        } catch (Exception $e) {
            self::$db->rollBack();
            $this->fail("Bet placement transaction failed: " . $e->getMessage());
        }

        // 3. Check if the bet was inserted
        $stmt = self::$db->prepare("SELECT * FROM bets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $bet = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($bet);
        $this->assertEquals($bet_amount, $bet['bet_amount']);

        // 4. Check if user's points were deducted
        $stmt = self::$db->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $points = $stmt->fetchColumn();
        $this->assertEquals(900, $points);
    }
}
