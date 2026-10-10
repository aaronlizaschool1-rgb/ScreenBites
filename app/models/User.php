<?php
/**
 * ScreenBites: User Model
 * 
 * Handles database interaction for accounts, password verification,
 * and user role queries against the `users` table.
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use PDO;

class User
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Locate an active user record by username
     *
     * @param string $username
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $sql = "SELECT user_id, full_name, username, password_hash, role, status 
                FROM users 
                WHERE username = :username 
                LIMIT 1";

        return $this->db->fetchOne($sql, [':username' => trim($username)]);
    }

    /**
     * Locate an active user record by ID
     *
     * @param int $userId
     * @return array<string, mixed>|null
     */
    public function findById(int $userId): ?array
    {
        $sql = "SELECT user_id, full_name, username, role, status, created_at 
                FROM users 
                WHERE user_id = :id 
                LIMIT 1";

        return $this->db->fetchOne($sql, [':id' =>$userId]);
    }

    /**
     * Validate user credentials against stored BCRYPT password hash
     *
     * @param string $username
     * @param string $plainPassword
     * @return array<string, mixed>|false Returns user array on success or false on failure
     */
    public function authenticate(string $username, string$plainPassword): array|false
    {
        $user = $this->findByUsername($username);

        if (!$user) {
            return false;
        }

        // Verify account status
        if ($user['status'] !== 'Active') {
            return false;
        }

        // Verify password match using native password_verify
        if (password_verify($plainPassword,$user['password_hash'])) {
            // Unset sensitive hash before passing data upward
            unset($user['password_hash']);
            return $user;
        }

        return false;
    }

    /**
     * Retrieve all system users (Administrator view)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllUsers(): array
    {
        $sql = "SELECT user_id, full_name, username, role, status, created_at 
                FROM users 
                ORDER BY created_at DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Create a new system user
     *
     * @param string $fullName
     * @param string $username
     * @param string $plainPassword
     * @param string $role Must match valid ENUM: 'Administrator', 'Ticketing Staff', 'Snackbar Cashier'
     * @return int Inserted user_id
     */
    public function create(string $fullName, string$username, string $plainPassword, string$role): int
    {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);

        $sql = "INSERT INTO users (full_name, username, password_hash, role, status) 
                VALUES (:full_name, :username, :password_hash, :role, 'Active')";

        $this->db->query($sql, [
            ':full_name'     => trim($fullName),
            ':username'      => trim($username),
            ':password_hash' => $hash,
            ':role'          => $role
        ]);

        return (int)$this->db->lastInsertId();
    }
}