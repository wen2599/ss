<?php
// 文件名: dotenv.php
// 路径: backend/core/dotenv.php
// 用途: 一个简单的纯PHP .env文件解析器

class DotEnv
{
    /**
     * 加载.env文件并将其变量设置到环境中
     *
     * @param string $path .env文件的路径
     * @return void
     * @throws \RuntimeException 如果文件不存在或不可读
     */
    public static function load(string $path): void
    {
        if (!is_readable($path)) {
            throw new \RuntimeException(sprintf('%s not found or not readable.', $path));
        }

        // 将文件读入数组，忽略空行和新行符
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // 跳过注释行
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // 将行按'='分割
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // 去除值两边的引号（单引号或双引号）
            if (strlen($value) > 1 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
                $value = substr($value, 1, -1);
            } elseif (strlen($value) > 1 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
                $value = substr($value, 1, -1);
            }

            // 设置环境变量
            // 确保不覆盖已存在的系统环境变量
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}