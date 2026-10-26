<?php

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

    public function findByUsername(string $username): ?array
    {
        $sql = "SELECT user_id, full_name, username, password_hash, role, status
                FROM users
                WHERE username = :username
                LIMIT 1";

        return $this->db->fetchOne($sql, [':username' => trim($username)]);
    }

    public function findById(int $userId): ?array
    {
        $sql = "SELECT user_id, full_name, username, role, status, created_at
                FROM users
                WHERE user_id = :id
                LIMIT 1";

        return $this->db->fetchOne($sql, [':id' =>$userId]);
    }

    public function authenticate(string $username, string$plainPassword): array|false
    {
        $user = $this->findByUsername($username);

        if (!$user) {
            return false;
        }

        if ($user['status'] !== 'Active') {
            return false;
        }

        if (password_verify($plainPassword,$user['password_hash'])) {

            unset($user['password_hash']);
            return $user;
        }

        return false;
    }

    public function getAllUsers(): array
    {
        $sql = "SELECT user_id, full_name, username, role, status, created_at
                FROM users
                ORDER BY created_at DESC";

        return $this->db->fetchAll($sql);
    }

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
