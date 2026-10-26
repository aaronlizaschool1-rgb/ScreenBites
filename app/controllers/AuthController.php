<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use App\Models\User;

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function index(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard');
            return;
        }

        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        $this->view('auth/login', [
            'error'     => $flashError,
            'csrfToken' => Auth::csrfToken()
        ], null);
    }

    public function authenticate(): void
    {

        $token = (string)$this->post('csrf_token', '');
        if (!Auth::verifyCsrfToken($token)) {
            $_SESSION['flash_error'] = 'Security validation failed (Invalid CSRF token). Please try again.';
            $this->redirect('login');
            return;
        }

        $username = trim((string)$this->post('username', ''));
        $password = (string)$this->post('password', '');

        if ($username === '' || $password === '') {
            $_SESSION['flash_error'] = 'Please enter both your username and password.';
            $this->redirect('login');
            return;
        }

        $user = $this->userModel->authenticate($username, $password);

        if (!$user) {

            $this->logFailedAttempt($username);

            $_SESSION['flash_error'] = 'Invalid username or password.';
            $this->redirect('login');
            return;
        }

        Auth::login($user);

        $this->redirectToRoleDashboard($user['role']);
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('login');
    }

    private function redirectToRoleDashboard(string $role): void
    {
        switch ($role) {
            case ROLE_ADMIN:
                $this->redirect('dashboard');
                break;
            case ROLE_TICKETING:
                $this->redirect('schedules');
                break;
            case ROLE_CASHIER:
                $this->redirect('pos');
                break;
            default:
                $this->redirect('dashboard');
                break;
        }
    }

    private function logFailedAttempt(string $attemptedUsername): void
    {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $existing = $this->userModel->findByUsername($attemptedUsername);
            $userId = $existing ? (int)$existing['user_id'] : 1;

            $sql = "INSERT INTO audit_logs (user_id, action_type, target_table, record_id, details, ip_address)
                    VALUES (:uid, 'LOGIN_FAILURE', 'users', :rid, :details, :ip)";

            $db->query($sql, [
                ':uid'     => $userId,
                ':rid'     => $userId,
                ':details' => "Failed authentication attempt for username: '{$attemptedUsername}'",
                ':ip'      => $ip
            ]);
        } catch (\Throwable $e) {

            error_log("[Auth Log Failure] " . $e->getMessage());
        }
    }
}
