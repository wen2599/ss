<?php
use PHPUnit\Framework\TestCase;

class ChatTest extends TestCase
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

        // Add some test users and a room
        $stmt = self::$db->prepare("INSERT INTO users (display_id, phone_number, password_hash, points) VALUES (?, ?, ?, ?)");
        $stmt->execute(['1111', '1111111111', password_hash('password', PASSWORD_DEFAULT), 1000]);

        $stmt = self::$db->prepare("INSERT INTO rooms (game_mode, room_code, state, created_at, updated_at) VALUES (?, ?, 'waiting', ?, ?)");
        $stmt->execute(['normal_2', 'test_room', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    }

    public function testSendMessage()
    {
        $room_id = 1;
        $user_id = 1;
        $message = 'Hello, world!';

        $stmt = self::$db->prepare("INSERT INTO chat_messages (room_id, user_id, message) VALUES (?, ?, ?)");
        $result = $stmt->execute([$room_id, $user_id, $message]);

        $this->assertTrue($result);

        $stmt = self::$db->prepare("SELECT * FROM chat_messages WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$room_id, $user_id]);
        $chat_message = $stmt->fetch();

        $this->assertEquals($message, $chat_message['message']);
    }

    public function testGetMessages()
    {
        $room_id = 1;

        $stmt = self::$db->prepare("SELECT cm.*, u.display_id FROM chat_messages cm JOIN users u ON cm.user_id = u.id WHERE cm.room_id = ? ORDER BY cm.created_at ASC");
        $stmt->execute([$room_id]);
        $messages = $stmt->fetchAll();

        $this->assertCount(1, $messages);
        $this->assertEquals('Hello, world!', $messages[0]['message']);
    }
}
