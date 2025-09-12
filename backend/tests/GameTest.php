<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../api/game.php';

class GameTest extends TestCase
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
        $stmt->execute(['2222', '2222222222', password_hash('password', PASSWORD_DEFAULT), 500]);

        $stmt = self::$db->prepare("INSERT INTO rooms (game_mode, room_code, state, created_at, updated_at) VALUES (?, ?, 'waiting', ?, ?)");
        $stmt->execute(['normal_2', 'test_room', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

        $stmt = self::$db->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, ?, (datetime('now','localtime')))");
        $stmt->execute([1, 1, 1]);
        $stmt->execute([1, 2, 2]);
    }

    public function testStartGame()
    {
        $game_id = Game::startGame(self::$db, 1);
        $this->assertIsInt($game_id);

        $stmt = self::$db->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch();
        $this->assertEquals('setting_hands', $game['game_state']);

        $stmt = self::$db->prepare("SELECT * FROM room_players WHERE room_id = ?");
        $stmt->execute([1]);
        $players = $stmt->fetchAll();
        foreach ($players as $player) {
            $this->assertNotNull($player['hand_cards']);
            $hand = json_decode($player['hand_cards'], true);
            $this->assertCount(13, $hand);
        }
    }

    public function testCardAnalyzer()
    {
        $analyzer = new ThirteenCardAnalyzer();

        // Test Straight Flush
        $hand = ['H2', 'H3', 'H4', 'H5', 'H6'];
        $details = $analyzer->analyze_hand($hand);
        $this->assertEquals(ThirteenCardAnalyzer::TYPE_STRAIGHT_FLUSH, $details['type']);

        // Test Four of a Kind
        $hand = ['S5', 'H5', 'D5', 'C5', 'HA'];
        $details = $analyzer->analyze_hand($hand);
        $this->assertEquals(ThirteenCardAnalyzer::TYPE_FOUR_OF_A_KIND, $details['type']);

        // Test Full House
        $hand = ['S5', 'H5', 'D5', 'CA', 'HA'];
        $details = $analyzer->analyze_hand($hand);
        $this->assertEquals(ThirteenCardAnalyzer::TYPE_FULL_HOUSE, $details['type']);

        // Test Flush
        $hand = ['H2', 'H7', 'H4', 'H9', 'H6'];
        $details = $analyzer->analyze_hand($hand);
        $this->assertEquals(ThirteenCardAnalyzer::TYPE_FLUSH, $details['type']);

        // Test Straight
        $hand = ['S2', 'H3', 'D4', 'C5', 'H6'];
        $details = $analyzer->analyze_hand($hand);
        $this->assertEquals(ThirteenCardAnalyzer::TYPE_STRAIGHT, $details['type']);
    }
}
