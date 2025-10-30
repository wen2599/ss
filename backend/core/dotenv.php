<?php
// 文件名: dotenv.php
// 路径: core/dotenv.php
class DotEnv {
    public static function load(string $path): void {
        if (!is_readable($path)) { throw new \RuntimeException(sprintf('%s not found or not readable.', $path)); }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name); $value = trim($value);
            if (strlen($value) > 1 && ($value[0] === '"' && $value[strlen($value) - 1] === '"' || $value[0] === "'" && $value[strlen($value) - 1] === "'")) {
                $value = substr($value, 1, -1);
            }
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value; $_SERVER[$name] = $value;
            }
        }
    }
}