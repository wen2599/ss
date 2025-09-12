<?php
use PHPUnit\Framework\TestCase;

class FriendsTest extends TestCase
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

    public function testAddFriend()
    {
        $user_id = 1;
        $friend_id = 2;

        $stmt = self::$db->prepare("INSERT INTO friends (user_id, friend_id) VALUES (?, ?)");
        $result = $stmt->execute([$user_id, $friend_id]);

        $this->assertTrue($result);

        $stmt = self::$db->prepare("SELECT * FROM friends WHERE user_id = ? AND friend_id = ?");
        $stmt->execute([$user_id, $friend_id]);
        $friendship = $stmt->fetch();

        $this->assertEquals('pending', $friendship['status']);
    }

    public function testAcceptFriend()
    {
        $user_id = 2;
        $friend_id = 1;

        $stmt = self::$db->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
        $result = $stmt->execute([$friend_id, $user_id]);

        $this->assertTrue($result);

        $stmt = self::$db->prepare("SELECT * FROM friends WHERE user_id = ? AND friend_id = ?");
        $stmt->execute([$friend_id, $user_id]);
        $friendship = $stmt->fetch();

        $this->assertEquals('accepted', $friendship['status']);
    }

    public function testGetFriends()
    {
        $user_id = 1;

        $stmt = self::$db->prepare("SELECT u.id, u.display_id, f.status FROM users u JOIN friends f ON u.id = f.friend_id WHERE f.user_id = ?");
        $stmt->execute([$user_id]);
        $friends = $stmt->fetchAll();

        $this->assertCount(1, $friends);
        $this->assertEquals('2222', $friends[0]['display_id']);
    }
}
