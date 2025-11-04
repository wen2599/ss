<?php
class Config {
    private static $config = null;
    
    public static function get($key) {
        if (self::$config === null) {
            self::loadConfig();
        }
        
        return isset(self::$config[$key]) ? self::$config[$key] : null;
    }
    
    private static function loadConfig() {
        $envFile = __DIR__ . '/.env';
        if (!file_exists($envFile)) {
            throw new Exception('.env file not found');
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::$config = [];
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            
            list($key, $value) = explode('=', $line, 2);
            self::$config[trim($key)] = trim($value);
        }
    }
}

class Database {
    private $connection;
    
    public function __construct() {
        $host = Config::get('DB_HOST');
        $port = Config::get('DB_PORT');
        $dbname = Config::get('DB_NAME');
        $username = Config::get('DB_USER');
        $password = Config::get('DB_PASS');
        
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        
        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
}
?>