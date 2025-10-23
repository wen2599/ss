<?php
// vendor/composer/ClassLoader.php

namespace Composer\Autoload;

class ClassLoader
{
    private static $loader;
    private $prefixLengthsPsr4 = [];
    private $prefixDirsPsr4 = [];
    private $fallbackDirsPsr4 = [];

    public function __construct()
    {
        // Manually configure the PSR-4 autoloading rules.
        // This is what `composer install` would typically generate into a different file.
        $this->prefixLengthsPsr4['App\\'] = [4];
        $this->prefixDirsPsr4['App\\'] = [__DIR__ . '/../../src'];
    }

    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }
        self::$loader = new self();
        self::$loader->register(true);
        return self::$loader;
    }

    public function register($prepend = false)
    {
        spl_autoload_register([$this, 'loadClass'], true, $prepend);
    }

    public function loadClass($class)
    {
        if ($file = $this->findFile($class)) {
            includeFile($file);
            return true;
        }
    }

    public function findFile($class)
    {
        $logicalPathPsr4 = strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';

        $first = $class[0];
        if (isset($this->prefixLengthsPsr4[$first])) {
            foreach ($this->prefixLengthsPsr4[$first] as $prefix => $length) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($this->prefixDirsPsr4[$prefix] as $dir) {
                        if (file_exists($file = $dir . DIRECTORY_SEPARATOR . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }

        // A simplified direct check for our specific "App\\" namespace
        if (strpos($class, 'App\\') === 0) {
            $subPath = substr($logicalPathPsr4, 4); // Length of "App\\"
            $file = __DIR__ . '/../../src/' . $subPath;
            if (file_exists($file)) {
                return $file;
            }
        }

        return false;
    }
}

function includeFile($file)
{
    include $file;
}
