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
        if (!file_exists($envFile) || !is_readable($envFile)) {
            throw new Exception('.env file not found or is not readable');
        }

        self::$config = [];
        $content = file_get_contents($envFile);
        $lines = preg_split('/(\r\n|\n|\r)/', $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $value = $matches[2];

                // Remove surrounding quotes (single or double) if they exist
                if (preg_match('/^"(.*)"$/', $value, $q_matches) || preg_match("/^'(.*)'$/", $value, $q_matches)) {
                    $value = $q_matches[1];
                }

                self::$config[$key] = $value;
            }
        }
    }
}

class Database {
    private $connection;
    
    public function __construct() {
        $host = Config::get('DB_HOST');

        // --- TEMPORARY DEBUGGING ---
        echo "Debug output for DB_HOST:\n";
        var_dump($host);
        exit;
        // -------------------------

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