<?php
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
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

    public function testRegisterUser()
    {
        $display_id = '1234';
        $phone = '1234567890';
        $password = 'password';
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = self::$db->prepare("INSERT INTO users (display_id, phone_number, password_hash, points) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$display_id, $phone, $password_hash, 1000]);

        $this->assertTrue($result);

        $stmt = self::$db->prepare("SELECT * FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        $this->assertEquals($display_id, $user['display_id']);
    }

    public function testLoginUser()
    {
        $phone = '1234567890';
        $password = 'password';

        $stmt = self::$db->prepare("SELECT * FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        $this->assertTrue(password_verify($password, $user['password_hash']));
    }
}
