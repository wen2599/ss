<?php
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
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
    }

    public function testFindUser()
    {
        $stmt = self::$db->prepare("SELECT display_id FROM users WHERE phone_number = ?");
        $stmt->execute(['1111111111']);
        $user = $stmt->fetch();

        $this->assertEquals('1111', $user['display_id']);
    }

    public function testTransferPoints()
    {
        $sender_id = 1; // display_id 1111
        $recipient_id = 2; // display_id 2222
        $amount = 100;

        self::$db->beginTransaction();

        $stmt = self::$db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $stmt->execute([$amount, $sender_id]);

        $stmt = self::$db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->execute([$amount, $recipient_id]);

        self::$db->commit();

        $stmt = self::$db->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$sender_id]);
        $sender_points = $stmt->fetchColumn();

        $stmt = self::$db->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$recipient_id]);
        $recipient_points = $stmt->fetchColumn();

        $this->assertEquals(900, $sender_points);
        $this->assertEquals(600, $recipient_points);
    }
}
