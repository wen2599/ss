<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../api/Router.php';
require_once __DIR__ . '/../api/Response.php';

class RouterTest extends TestCase
{
    public function testAddAndDispatchRoute()
    {
        $router = new Router();
        $testFile = 'test_handler.php';
        file_put_contents($testFile, '<?php echo "test";');

        $router->add_route('GET', 'test', $testFile);

        ob_start();
        $router->dispatch('GET', 'test');
        $output = ob_get_clean();

        $this->assertEquals('test', $output);

        unlink($testFile);
    }
}
