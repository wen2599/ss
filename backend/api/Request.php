<?php

class Request {
    public $method;
    public $endpoint;
    public $data;

    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->endpoint = $_GET['endpoint'] ?? '';
        $this->data = json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
