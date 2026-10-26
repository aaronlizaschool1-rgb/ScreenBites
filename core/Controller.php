<?php

declare(strict_types=1);

namespace Core;

abstract class Controller
{

    protected function view(string $view, array $data = [], ?string $layout = 'layouts'): void
    {

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

            require $viewPath;
        }
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    protected function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function redirect(string $path): void
    {
        $destination = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
        header("Location: {$destination}");
        exit;
    }
}
