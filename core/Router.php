<?php

declare(strict_types=1);

namespace Core;

class Router
{

    private array $routes = [];

    public function get(string $path, string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, string $handler): void
    {
        $trimmedPath = trim($path, '/');

        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $trimmedPath);
        $regex = '#^' . ($pattern === '' ? '' : $pattern) . '$#';

        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $regex,
            'handler' => $handler
        ];
    }

    private function getRequestUri(): string
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);

        $baseDir = str_replace('\\', '/', $baseDir);

        if ($baseDir !== '/' && strpos($requestUri, $baseDir) === 0) {
            $requestUri = substr($requestUri, strlen($baseDir));
        }

        return trim($requestUri, '/');
    }

    public function dispatch(): void
    {
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($requestMethod === 'HEAD') {
            $requestMethod = 'GET';
        }
        $requestUri = $this->getRequestUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            if (preg_match($route['pattern'], $requestUri, $matches)) {

                $params = array_filter(
                    $matches,
                    fn($key) => !is_numeric($key),
                    ARRAY_FILTER_USE_KEY
                );

                $this->executeHandler($route['handler'], $params);
                return;
            }
        }

        $this->sendNotFoundResponse();
    }

    private function executeHandler(string $handler, array $params): void
    {
        [$controllerName, $method] = explode('@', $handler);
        $fullyQualifiedController = "App\\Controllers\\" . $controllerName;

        if (!class_exists($fullyQualifiedController)) {
            http_response_code(500);
            echo "Routing Error: Controller [{$fullyQualifiedController}] was not found.";
            return;
        }

        $controllerInstance = new $fullyQualifiedController();

        if (!method_exists($controllerInstance, $method)) {
            http_response_code(500);
            echo "Routing Error: Method [{$method}] does not exist on [{$fullyQualifiedController}].";
            return;
        }

        call_user_func_array([$controllerInstance, $method], array_values($params));
    }

    private function sendNotFoundResponse(): void
    {
        http_response_code(404);

        if ($this->isAjaxOrJsonRequest()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Requested endpoint was not found on this server.'
            ], JSON_PRETTY_PRINT);
            return;
        }

        echo '<div style="font-family: Arial, sans-serif; text-align: center; margin-top: 100px;">';
        echo '<h1 style="font-size: 48px; color: #dc3545;">404</h1>';
        echo '<h2>Page Not Found</h2>';
        echo '<p>The requested route does not exist in ScreenBites.</p>';
        echo '<a href="' . BASE_URL . '/login" style="color: #007bff; text-decoration: none;">Return to Home</a>';
        echo '</div>';
    }

    private function isAjaxOrJsonRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    }
}
