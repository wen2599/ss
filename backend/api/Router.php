<?php

class Router {
    private $routes = [];

    public function add_route($method, $endpoint, $handler) {
        $this->routes[] = [
            'method' => $method,
            'endpoint' => $endpoint,
            'handler' => $handler
        ];
    }

    public function dispatch($method, $endpoint) {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['endpoint'] === $endpoint) {
                require_once $route['handler'];
                return;
            }
        }
        send_json_error(404, 'Endpoint not found');
    }
}
