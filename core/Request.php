<?php

namespace App\Core;

class Request
{
    private array $body = [];
    private array $queryParams = [];
    private array $headers = [];

    public function __construct()
    {
        $this->queryParams = $_GET;
        $this->headers = $this->extractHeaders();
        $this->parseBody();
    }

    private function extractHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }
        return $headers;
    }

    private function parseBody(): void
    {
        $contentType = $this->getHeader('Content-Type') ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                $this->body = $decoded;
            }
        } else {
            $this->body = $_POST;
        }
    }

    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function getUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function get(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    public function query(string $key, $default = null)
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getHeader(string $key): ?string
    {
        return $this->headers[$key] ?? null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
