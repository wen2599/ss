<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>后端诊断脚本</h1>";
echo "<p>本脚本将测试您服务器配置的几个关键方面。</p><hr>";

// Test 1: .env file reading
echo "<h2>测试 1: 读取 .env 文件</h2>";
$env_path = __DIR__ . '/.env';
if (!file_exists($env_path)) {
    echo "<p><font color='red'><b>错误:</b> 在目录 " . __DIR__ . " 中未找到 .env 文件！</font></p>";
} else {
    echo "<p><font color='green'>成功:</font> 已找到 .env 文件。</p>";
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim(trim($value), '"\'');
    }
    echo "<p>.env 文件已成功加载。</p>";
}
echo "<hr>";

// Test 2: Database Connection
echo "<h2>测试 2: 数据库连接</h2>";
$host = $_ENV['DB_HOST'] ?? null;
$dbname = $_ENV['DB_NAME'] ?? null;
$user = $_ENV['DB_USER'] ?? null;
$pass = $_ENV['DB_PASS'] ?? '';
if (!$host || !$dbname || !$user) {
    echo "<p><font color='red'><b>错误:</b> .env 文件中的数据库配置不完整。</font></p>";
} else {
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p><font color='green'><b>成功:</b> 数据库连接正常。</font></p>";
    } catch (PDOException $e) {
        echo "<p><font color='red'><b>错误:</b> 数据库连接失败。</font></p>";
        echo "<p>错误信息: " . $e->getMessage() . "</p>";
    }
}
echo "<hr>";

// Test 3: PHP Session Functionality
echo "<h2>测试 3: PHP Session 功能</h2>";
$session_path = session_save_path();
echo "<p>Session 存储路径: <code>" . htmlspecialchars($session_path) . "</code></p>";
if (empty($session_path)) {
     echo "<p><font color='orange'><b>警告:</b> Session 存储路径为空。这可能会导致问题。</font></p>";
} elseif (!is_writable($session_path)) {
    echo "<p><font color='red'><b>警告:</b> Session 存储路径不可写！这是导致502错误的常见原因。</font></p>";
} else {
    echo "<p><font color='green'>信息:</font> Session 存储路径可写。</p>";
}

// Attempt to start and use a session
try {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['test_variable'] = 'hello_world';
    if (isset($_SESSION['test_variable']) && $_SESSION['test_variable'] === 'hello_world') {
        echo "<p><font color='green'><b>成功:</b> PHP Session 已成功启动、写入和读取。</font></p>";
    } else {
        echo "<p><font color='red'><b>错误:</b> Session 可以启动，但无法正确读写 Session 变量。</font></p>";
    }
    session_destroy();
} catch (Throwable $e) {
    echo "<p><font color='red'><b>错误:</b> 启动或使用 Session 时发生错误。</font></p>";
    echo "<p>错误信息: " . $e->getMessage() . "</p>";
}
echo "<hr>";

echo "<h2>诊断完成</h2>";
?>