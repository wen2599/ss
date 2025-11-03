<?php
// 文件: backend/database_setup.php (v3 - 智能寻找 .env 版本)

/**
 * 智能加载 .env 文件。
 * 它会按照优先级顺序在多个常见位置寻找 .env 文件，并加载第一个找到的。
 *
 * @return string|null 成功加载的 .env 文件的路径，如果都找不到则返回 null。
 */
function loadEnvSmart() {
    // 定义要搜索的路径列表 (按优先级排序)
    $paths = [
        __DIR__ . '/.env',                         // 1. 脚本当前目录 (最高优先级)
        dirname(__DIR__) . '/.env',                // 2. 上一级目录
        (getenv('HOME') ? getenv('HOME') . '/.env' : null) // 3. 用户主目录 (例如 /usr/home/wenge95222/.env)
    ];

    $foundPath = null;

    foreach ($paths as $path) {
        if ($path && file_exists($path)) {
            $foundPath = $path;
            break; // 找到第一个就停止搜索
        }
    }

    if (!$foundPath) {
        fwrite(STDERR, "错误: 在所有预设路径中都找不到 .env 文件。\n");
        fwrite(STDERR, "请确保 .env 文件存在于以下任一位置：\n");
        foreach ($paths as $p) { if ($p) fwrite(STDERR, " - {$p}\n"); }
        exit(1);
    }
    
    echo "成功找到并加载配置文件: {$foundPath}\n";

    $lines = file($foundPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            // 关键修正：强制去除值两边的引号
            $value = trim(trim($value), '"'');
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return $foundPath;
}

// ---- 主逻辑开始 ----

// 使用全新的智能加载函数
loadEnvSmart();

// 从环境变量中获取配置
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

if (!$host || !$user || !$pass || !$db_name) {
    fwrite(STDERR, "错误: .env 文件中数据库配置不完整。请确保 DB_HOST, DB_USER, DB_PASS, DB_NAME 都已设置。\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    echo "正在使用主机 '{$host}' 连接数据库...\n";
    $conn = new mysqli($host, $user, $pass, $db_name);
    $conn->set_charset("utf8mb4");

    echo "\n[成功] 数据库连接成功！\n\n";

    // --- 创建 users 表 ---
    $sql_users = "CREATE TABLE IF NOT EXISTS `users` ( `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, `email` VARCHAR(191) NOT NULL, `password` VARCHAR(255) NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `email_unique` (`email`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql_users)) echo "数据表 'users' 创建成功或已存在。\n";

    // --- 创建 lottery_numbers 表 ---
    $sql_numbers = "CREATE TABLE IF NOT EXISTS `lottery_numbers` ( `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, `number` VARCHAR(255) NOT NULL, `issue_date` DATE NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `issue_date_unique` (`issue_date`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql_numbers)) echo "数据表 'lottery_numbers' 创建成功或已存在。\n";

    echo "\n数据库初始化流程全部完成。\n";

} catch (mysqli_sql_exception $e) {
    fwrite(STDERR, "\n[失败] 数据库操作失败: " . $e->getMessage() . "\n");
    fwrite(STDERR, "错误码: " . $e->getCode() . "\n");
    if ($e->getCode() === 2002) {
        fwrite(STDERR, "提示: 无法连接到数据库主机 '{$host}'。请检查主机名是否正确以及服务器网络。\n");
    } elseif ($e->getCode() === 1045) {
        fwrite(STDERR, "提示: 访问被拒绝。这通常意味着用户名或密码错误。\n");
    }
    exit(1);
} finally {
    if (isset($conn)) $conn->close();
}

exit(0);
?>