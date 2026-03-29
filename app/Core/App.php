<?php

namespace App\Core;

class App
{
    private array $config;
    private array $routes;

    public function __construct(array $config, array $routes)
    {
        $this->config = $config;
        $this->routes = $routes;
        date_default_timezone_set($config['app']['timezone'] ?? 'UTC');
    }

    public function run(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $this->getPath();

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($method !== $routeMethod) {
                continue;
            }

            $pattern = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([0-9]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $this->dispatch($handler, $matches);
                return;
            }
        }

        http_response_code(404);
        echo '404 - Page not found';
    }

    private function dispatch(string $handler, array $params): void
    {
        [$controllerName, $action] = explode('@', $handler);
        $fullController = 'App\\Controllers\\' . $controllerName;

        if (!class_exists($fullController)) {
            http_response_code(500);
            exit('Controller not available.');
        }

        $controller = new $fullController($this->config);

        if (!method_exists($controller, $action)) {
            http_response_code(500);
            exit('Action not available.');
        }

        $controller->$action(...$params);
    }

    private function getPath(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '/' && str_starts_with($uri, $scriptName)) {
            $uri = substr($uri, strlen($scriptName));
        }

        $uri = '/' . trim($uri, '/');
        return $uri === '//' ? '/' : $uri;
    }
}
