<?php
// temporary_db_setup.php
// A standalone script to set up the database without any external dependencies.
// Run from the project root: php temporary_db_setup.php

// --- START: Self-contained .env loader ---
function load_env($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
// --- END: Self-contained .env loader ---

echo "--- Starting Standalone Database Setup ---\n";

// 1. Load Environment Variables from .env in the current directory
$dotenv_path = __DIR__ . '/.env';
if (!file_exists($dotenv_path)) {
    die("CRITICAL ERROR: .env file not found in the root directory ({$dotenv_path}). Please ensure the file exists and contains your DB credentials.\n");
}
load_env($dotenv_path);
echo "[✓] .env file loaded.\n";

// 2. Get Database Credentials
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

if (!$db_host || !$db_user || !$db_pass || !$db_name) {
    die("CRITICAL ERROR: One or more database environment variables (DB_HOST, DB_USER, DB_PASS, DB_NAME) are not set in your .env file.\n");
}
echo "[✓] Database credentials retrieved from .env.\n";

// 3. Connect to the Database
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    echo "[✓] Successfully connected to the database '{$db_name}'.\n";
} catch (Exception $e) {
    die("DATABASE CONNECTION FAILED: " . $e->getMessage() . "\nPlease check your DB credentials in the .env file and ensure the database exists and is accessible.\n");
}

// 4. Define and Execute SQL Queries
$queries = [
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(255) UNIQUE NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) UNIQUE NULL,
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `ai_prompts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) UNIQUE NOT NULL,
        `model` VARCHAR(255) NOT NULL,
        `prompt` TEXT NOT NULL,
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `bills` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL,
        `bill_id` VARCHAR(255) UNIQUE NOT NULL,
        `sender` VARCHAR(255) NOT NULL,
        `total_amount` DECIMAL(10, 2) NOT NULL,
        `details` JSON NOT NULL,
        `body_html` TEXT NULL,
        `received_at` TIMESTAMP NOT NULL,
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `user_states` (
        `user_id` BIGINT UNSIGNED PRIMARY KEY,
        `state` VARCHAR(255) NOT NULL,
        `state_data` JSON NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `allowed_emails` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) UNIQUE NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `api_keys` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `key_name` VARCHAR(255) UNIQUE NOT NULL,
        `key_value` TEXT NOT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

echo "\n--- Creating tables ---\n";
$all_successful = true;
foreach ($queries as $query) {
    preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $query, $matches);
    $table_name = $matches[1] ?? 'unknown';

    if ($conn->query($query) === TRUE) {
        echo "[✓] Table '{$table_name}' created or already exists.\n";
    } else {
        echo "[✗] ERROR creating table '{$table_name}': " . $conn->error . "\n";
        $all_successful = false;
    }
}

// 5. Seed Data
$deepseek_prompt_text = <<<'EOT'
你是一个专门解析投注单据的AI。严格按照指定的JSON格式从文本中提取信息。不要添加任何说明或评论。如果某个字段找不到对应信息，则其值应为null。

```json
{
  "bill_id": "string | null",
  "sender": "string | null",
  "total_amount": "number | null",
  "details": [
    {
      "item": "string",
      "amount": "number",
      "result": "string<win/loss/draw>"
    }
  ]
}
```
EOT;

// Use prepared statement to prevent SQL injection, even with our own data.
$stmt = $conn->prepare(
    "INSERT INTO `ai_prompts` (name, model, prompt, created_at, updated_at)
     VALUES ('betting_slip_parser', 'deepseek-chat', ?, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
     model = VALUES(model), prompt = VALUES(prompt), updated_at = NOW()"
);
$stmt->bind_param("s", $deepseek_prompt_text);

echo "\n--- Seeding data ---\n";
if ($stmt->execute()) {
    echo "[✓] Seeded/Updated 'betting_slip_parser' prompt.\n";
} else {
    echo "[✗] ERROR seeding prompt: " . $stmt->error . "\n";
    $all_successful = false;
}
$stmt->close();

// 6. Close Connection and Report
$conn->close();
echo "\n--- Database Setup Finished ---\n";
if ($all_successful) {
    echo "SUCCESS: All tables were created and seeded successfully.\n";
} else {
    echo "WARNING: Some errors occurred during setup. Please review the output above.\n";
}

?>