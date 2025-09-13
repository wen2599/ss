<?php
use PHPUnit\Framework\TestCase;

class DrawsTest extends TestCase
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

    public function testCreateAndGetDraws()
    {
        // Test creating a draw
        $stmt = self::$db->prepare("INSERT INTO lottery_draws (draw_number, draw_date) VALUES (?, ?)");
        $stmt->execute(['2025001', '2025-01-01']);

        // Test getting draws
        $stmt = self::$db->query("SELECT * FROM lottery_draws WHERE draw_number = '2025001'");
        $draw = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('2025001', $draw['draw_number']);
        $this->assertEquals('2025-01-01', $draw['draw_date']);
    }
}
