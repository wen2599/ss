<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../api/Response.php';

class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Response::$is_testing = true;
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendJson()
    {
        ob_start();
        Response::send_json(['foo' => 'bar']);
        $output = ob_get_clean();

        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendJsonError()
    {
        ob_start();
        Response::send_json_error(404, 'Not Found');
        $output = ob_get_clean();

        $this->assertJsonStringEqualsJsonString('{"success":false,"message":"Not Found"}', $output);
    }
}
