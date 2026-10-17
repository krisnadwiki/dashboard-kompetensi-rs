<?php
class Router
{
    private array $routes = [];
    private string $uri;
    private string $method;

    public function __construct(string $uri, string $method)
    {
        // Strip /index.php prefix if present
        $this->uri    = rtrim(preg_replace('#^/index\.php#', '', strtok($uri, '?')), '/') ?: '/';
        $this->method = strtoupper($method);
    }

    public function get(string $path, string $file): void
    {
        $this->routes['GET'][$path] = $file;
    }

    public function post(string $path, string $file): void
    {
        $this->routes['POST'][$path] = $file;
    }

    public function dispatch(): void
    {
        $file = $this->routes[$this->method][$this->uri]
             ?? $this->routes['GET'][$this->uri]
             ?? null;

        if ($file && file_exists($file)) {
            require $file;
        } else {
            http_response_code(404);
            require VIEWS . '/errors/404.php';
        }
    }
}
