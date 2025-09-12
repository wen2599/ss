<?php
use PHPUnit\Framework\TestCase;

class LeaderboardTest extends TestCase
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

        // Add some test users
        $stmt = self::$db->prepare("INSERT INTO users (display_id, phone_number, password_hash, points) VALUES (?, ?, ?, ?)");
        $stmt->execute(['1111', '1111111111', password_hash('password', PASSWORD_DEFAULT), 1000]);
        $stmt->execute(['2222', '2222222222', password_hash('password', PASSWORD_DEFAULT), 500]);
        $stmt->execute(['3333', '3333333333', password_hash('password', PASSWORD_DEFAULT), 1500]);
    }

    public function testGetLeaderboard()
    {
        $stmt = self::$db->prepare("SELECT display_id, points FROM users ORDER BY points DESC LIMIT 10");
        $stmt->execute();
        $leaderboard = $stmt->fetchAll();

        $this->assertCount(3, $leaderboard);
        $this->assertEquals('3333', $leaderboard[0]['display_id']);
        $this->assertEquals(1500, $leaderboard[0]['points']);
        $this->assertEquals('1111', $leaderboard[1]['display_id']);
        $this->assertEquals(1000, $leaderboard[1]['points']);
        $this->assertEquals('2222', $leaderboard[2]['display_id']);
        $this->assertEquals(500, $leaderboard[2]['points']);
    }
}
