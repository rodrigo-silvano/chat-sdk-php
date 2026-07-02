<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, $handler): void
    {
        $this->routes['GET'][$this->convertToRegex($path)] = $handler;
    }

    public function post(string $path, $handler): void
    {
        $this->routes['POST'][$this->convertToRegex($path)] = $handler;
    }

    public function put(string $path, $handler): void
    {
        $this->routes['PUT'][$this->convertToRegex($path)] = $handler;
    }

    public function delete(string $path, $handler): void
    {
        $this->routes['DELETE'][$this->convertToRegex($path)] = $handler;
    }

    public function options(string $path, $handler): void
    {
        $this->routes['OPTIONS'][$this->convertToRegex($path)] = $handler;
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/:[a-zA-Z0-9_]+/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(string $method, string $uri): void
    {
        $this->handleCors();

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $path = parse_url($uri, PHP_URL_PATH);
        
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/' && strpos($path, $scriptName) === 0) {
            $path = substr($path, strlen($scriptName));
        }
        
        $path = '/' . ltrim($path, '/');

        if (!isset($this->routes[$method])) {
            $this->sendNotFound();
            return;
        }

        foreach ($this->routes[$method] as $routeRegex => $handler) {
            if (preg_match($routeRegex, $path, $matches)) {
                array_shift($matches);
                $this->executeHandler($handler, $matches);
                return;
            }
        }

        $this->sendNotFound();
    }

    private function executeHandler($handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (class_exists($class)) {
                $instance = new $class();
                if (method_exists($instance, $method)) {
                    call_user_func_array([$instance, $method], $params);
                    return;
                }
            }
        }

        $this->sendInternalError();
    }

    private function handleCors(): void
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Max-Age: 86400");
    }

    private function sendNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found']);
        exit;
    }

    private function sendInternalError(): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal Server Error']);
        exit;
    }
}
