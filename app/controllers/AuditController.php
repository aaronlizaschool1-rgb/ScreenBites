<?php
/**
 * ScreenBites: Authentication Controller
 * 
 * Handles user login views, credential authentication, session delegation,
 * role-directed dashboard routing, and user logouts.
 * 
 * @author Aaron Louis F. Macapagal
 */

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

    /**
     * Render the login page or redirect if already authenticated
     */
    public function index(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard');
            return;
        }

        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        // Renders app/views/auth/login.php without master header/sidebar layouts
        $this->view('auth/login', [
            'error'     => $flashError,
            'csrfToken' => Auth::csrfToken()
        ], null);
    }

    /**
     * Process login form submission via POST
     */
    public function authenticate(): void
    {
        // 1. Verify CSRF Token
        $token = (string)$this->post('csrf_token', '');
        if (!Auth::verifyCsrfToken($token)) {
            $_SESSION['flash_error'] = 'Security validation failed (Invalid CSRF token). Please try again.';
            $this->redirect('login');
            return;
        }

        $username = trim((string)$this->post('username', ''));
        $password = (string)$this->post('password', '');

        // 2. Validate non-empty fields
        if ($username === '' || $password === '') {
            $_SESSION['flash_error'] = 'Please enter both your username and password.';
            $this->redirect('login');
            return;
        }

        // 3. Attempt Authentication
        $user = $this->userModel->authenticate($username, $password);

        if (!$user) {
            // Log security incident to audit_logs (FR16)
            $this->logFailedAttempt($username);

            $_SESSION['flash_error'] = 'Invalid username or password.';
            $this->redirect('login');
            return;
        }

        // 4. Log the user into session
        Auth::login($user);

        // 5. Redirect based on assigned role (FR13)
        $this->redirectToRoleDashboard($user['role']);
    }

    /**
     * Log user out and terminate session
     */
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('login');
    }

    /**
     * Direct the user to their designated workspace based on RBAC permissions
     */
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

    /**
     * Helper to log authentication failures to the audit table (FR16)
     */
    private function logFailedAttempt(string $attemptedUsername): void
    {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            
            // Check if user exists to link record_id, else default to 0
            $existing = $this->userModel->findByUsername($attemptedUsername);
            $userId = $existing ? (int)$existing['user_id'] : 1; // Fallback to system admin ID for foreign key

            $sql = "INSERT INTO audit_logs (user_id, action_type, target_table, record_id, details, ip_address) 
                    VALUES (:uid, 'LOGIN_FAILURE', 'users', :rid, :details, :ip)";

            $db->query($sql, [
                ':uid'     => $userId,
                ':rid'     => $userId,
                ':details' => "Failed authentication attempt for username: '{$attemptedUsername}'",
                ':ip'      => $ip
            ]);
        } catch (\Throwable $e) {
            // Fail silently so DB logging issues do not disrupt login responses
            error_log("[Auth Log Failure] " . $e->getMessage());
        }
    }
}