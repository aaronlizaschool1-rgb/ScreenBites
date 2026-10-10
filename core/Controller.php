<?php
/**
 * ScreenBites: Base Controller Interface
 * 
 * Provides common controller utilities including view rendering with layout
 * wrappers, input handling, JSON API formatting, and session redirects.
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace Core;

abstract class Controller
{
    /**
     * Render a PHP view template enclosed within system layout wrappers
     * 
     * @param string $view Relative view path without extension (e.g., 'ticketing/seat_matrix')
     * @param array<string, mixed> $data Data payload passed to the view
     * @param string|null $layout Subdirectory in views containing header/sidebar/footer, or null for partials
     */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts'): void
    {
        // Extract variables to local scope
        extract($data, EXTR_SKIP);

        $viewPath = APP_PATH . '/views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(500);
            echo "Rendering Error: View template not found at [{$viewPath}].";
            return;
        }

        if ($layout !== null) {
            $headerPath  = APP_PATH . '/views/' . $layout . '/header.php';
            $sidebarPath = APP_PATH . '/views/' . $layout . '/sidebar.php';
            $footerPath  = APP_PATH . '/views/' . $layout . '/footer.php';

            if (file_exists($headerPath)) {
                require_once $headerPath;
            }
            if (file_exists($sidebarPath)) {
                require_once $sidebarPath;
            }

            require $viewPath;

            if (file_exists($footerPath)) {
                require_once $footerPath;
            }
        } else {
            // Standalone render (receipt printing, modals, standalone export)
            require $viewPath;
        }
    }

    /**
     * Send a structured JSON response and terminate execution
     * 
     * @param array<string, mixed> $data Payload to encode
     * @param int $statusCode HTTP status code (default: 200)
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Retrieve and clean POST parameters
     * 
     * @param string|null $key Specific parameter key or null for entire array
     * @param mixed $default Fallback value if key is not present
     */
    protected function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    /**
     * Retrieve and clean GET query parameters
     * 
     * @param string|null $key Specific parameter key or null for entire array
     * @param mixed $default Fallback value if key is not present
     */
    protected function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Retrieve raw JSON payload from request body (used for modern fetch / REST requests)
     * 
     * @return array<string, mixed>
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Redirect to an application route relative to BASE_URL
     * 
     * @param string $path Target relative path (e.g., 'dashboard' or 'login')
     */
    protected function redirect(string $path): void
    {
        $destination = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
        header("Location: {$destination}");
        exit;
    }
}