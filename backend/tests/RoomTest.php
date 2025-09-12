<?php
use PHPUnit\Framework\TestCase;

class RoomTest extends TestCase
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

    public function testCreateRoom()
    {
        $room_code = 'test_room';
        $game_mode = 'normal_2';
        $created_at = date('Y-m-d H:i:s');

        $stmt = self::$db->prepare("INSERT INTO rooms (game_mode, room_code, state, created_at, updated_at) VALUES (?, ?, 'waiting', ?, ?)");
        $result = $stmt->execute([$game_mode, $room_code, $created_at, $created_at]);

        $this->assertTrue($result);

        $stmt = self::$db->prepare("SELECT * FROM rooms WHERE room_code = ?");
        $stmt->execute([$room_code]);
        $room = $stmt->fetch();

        $this->assertEquals($game_mode, $room['game_mode']);
    }

    public function testJoinRoom()
    {
        $room_id = 1;
        $user_id = 1;
        $seat = 1;

        $stmt = self::$db->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, ?, (datetime('now','localtime')))");
        $result = $stmt->execute([$room_id, $user_id, $seat]);

        $this->assertTrue($result);

        $stmt = self::$db->prepare("SELECT * FROM room_players WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$room_id, $user_id]);
        $player = $stmt->fetch();

        $this->assertEquals($seat, $player['seat']);
    }
}
