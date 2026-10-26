<?php

declare(strict_types=1);

namespace Core;

class Auth
{
    private const SESSION_USER_KEY = 'sb_user';
    private const CSRF_TOKEN_KEY   = 'sb_csrf_token';

    private static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::initSession();

        session_regenerate_id(true);

        $_SESSION[self::SESSION_USER_KEY] = [
            'id'        => (int)$user['user_id'],
            'full_name' => $user['full_name'],
            'username'  => $user['username'],
            'role'      => $user['role'],
            'logged_at' => time()
        ];
    }

    public static function logout(): void
    {
        self::initSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool
    {
        self::initSession();
        return isset($_SESSION[self::SESSION_USER_KEY]['id']);
    }

    public static function user(): ?array
    {
        self::initSession();
        return $_SESSION[self::SESSION_USER_KEY] ?? null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function hasRole(string|array $roles): bool
    {
        $currentRole = self::role();
        if (!$currentRole) {
            return false;
        }

        if (is_string($roles)) {
            return $currentRole === $roles;
        }

        return in_array($currentRole, $roles, true);
    }

    public static function requireRole(string|array $allowedRoles = []): void
    {
        if (!self::check()) {
            $_SESSION['flash_error'] = 'Please sign in to access this resource.';
            header('Location: ' . rtrim(BASE_URL, '/') . '/login');
            exit;
        }

        if (!empty($allowedRoles)) {
            $roles = (array)$allowedRoles;
            if (!self::hasRole($roles)) {
                http_response_code(403);

                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Access Denied: You lack permissions to perform this action.'
                    ]);
                    exit;
                }

                echo '<div style="font-family: Arial, sans-serif; text-align: center; margin-top: 100px;">';
                echo '<h1 style="color: #c82333; font-size: 40px;">403 Forbidden</h1>';
                echo '<p>You do not have administrative privileges to access this module.</p>';
                echo '<a href="' . BASE_URL . '/dashboard" style="color: #007bff;">Back to Dashboard</a>';
                echo '</div>';
                exit;
            }
        }
    }

    public static function requireLogin(): void
    {
        self::requireRole();
    }

    public static function csrfToken(): string
    {
        self::initSession();
        if (empty($_SESSION[self::CSRF_TOKEN_KEY])) {
            $_SESSION[self::CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_TOKEN_KEY];
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        self::initSession();
        if (empty($_SESSION[self::CSRF_TOKEN_KEY]) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION[self::CSRF_TOKEN_KEY], $token);
    }
}
