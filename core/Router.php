<?php
/**
 * ScreenBites: Request Router Engine
 * 
 * Handles URL extraction, regex parameter compiling, HTTP verb validation,
 * and controller delegation.
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace Core;

class Router
{
    /**
     * Registered route registry
     * @var array<int, array{method: string, pattern: string, handler: string}>
     */
    private array $routes = [];

    /**
     * Register a GET route
     */
    public function get(string $path, string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Internal method to compile parameterized route paths to regex patterns
     */
    private function addRoute(string $method, string $path, string $handler): void
    {
        $trimmedPath = trim($path, '/');

        // Transform {param} segments into named regex capturing groups
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $trimmedPath);
        $regex = '#^' . ($pattern === '' ? '' : $pattern) . '$#';

        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $regex,
            'handler' => $handler
        ];
    }

    /**
     * Normalize the current request URI across root domains or subdirectories in XAMPP
     */
    private function getRequestUri(): string
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);

        // Normalize Windows backslashes
        $baseDir = str_replace('\\', '/', $baseDir);

        if ($baseDir !== '/' && strpos($requestUri, $baseDir) === 0) {
            $requestUri = substr($requestUri, strlen($baseDir));
        }

        return trim($requestUri, '/');
    }

    /**
     * Match current request against registry and dispatch
     */
    public function dispatch(): void
    {
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $requestUri = $this->getRequestUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            if (preg_match($route['pattern'], $requestUri, $matches)) {
                // Filter only named parameter captures
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

    /**
     * Instantiate the controller and call the targeted action
     */
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

        call_user_func_array([$controllerInstance, $method], $params);
    }

    /**
     * Return 404 response for unresolved endpoints
     */
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

    /**
     * Check if the incoming request expects a JSON payload
     */
    private function isAjaxOrJsonRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    }
}