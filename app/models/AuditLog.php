<?php


declare(strict_types=1);

namespace App\Models;

use Core\Database;
use PDO;

class AuditLog
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Insert a new record into audit_logs
     *
     * @param array{
     *   user_id: int,
     *   action_type: string,
     *   target_table: string,
     *   record_id: int,
     *   details: string,
     *   ip_address: string
     * } $data
     * @return bool
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO audit_logs (
                    user_id,
                    action_type,
                    target_table,
                    record_id,
                    details,
                    ip_address,
                    created_at
                ) VALUES (
                    :user_id,
                    :action_type,
                    :target_table,
                    :record_id,
                    :details,
                    :ip_address,
                    NOW()
                )";

        $stmt = $this->db->query($sql, [
            ':user_id'      => $data['user_id'],
            ':action_type'  => $data['action_type'],
            ':target_table' => $data['target_table'],
            ':record_id'    => $data['record_id'],
            ':details'      => $data['details'],
            ':ip_address'   => $data['ip_address']
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Retrieve paginated and filtered audit logs joined with the actor's user profile
     *
     * @param array<string, mixed> $filters
     * @param int $limit
     * @param int $offset
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(array $filters = [], int $limit = 50, int$offset = 0): array
    {
        $params = [];$whereClauses = [];

        if (!empty($filters['action_type'])) {$whereClauses[] = "a.action_type = :action_type";
            $params[':action_type'] =$filters['action_type'];
        }

        if (!empty($filters['user_id'])) {$whereClauses[] = "a.user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (!empty($filters['start_date'])) {$whereClauses[] = "DATE(a.created_at) >= :start_date";
            $params[':start_date'] =$filters['start_date'];
        }

        if (!empty($filters['end_date'])) {$whereClauses[] = "DATE(a.created_at) <= :end_date";
            $params[':end_date'] =$filters['end_date'];
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $sql = "SELECT 
                    a.log_id,
                    a.action_type,
                    a.target_table,
                    a.record_id,
                    a.details,
                    a.ip_address,
                    a.created_at,
                    u.user_id,
                    u.full_name AS actor_name,
                    u.username AS actor_username,
                    u.role AS actor_role
                FROM audit_logs a
                INNER JOIN users u ON a.user_id = u.user_id
                {$whereSql}
                ORDER BY a.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($sql,$params);
    }

    /**
     * Get total count of logs matching active filters
     *
     * @param array<string, mixed> $filters
     * @return int
     */
    public function countLogs(array $filters = []): int
    {
        $params = [];$whereClauses = [];

        if (!empty($filters['action_type'])) {$whereClauses[] = "action_type = :action_type";
            $params[':action_type'] =$filters['action_type'];
        }

        if (!empty($filters['user_id'])) {$whereClauses[] = "user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (!empty($filters['start_date'])) {$whereClauses[] = "DATE(created_at) >= :start_date";
            $params[':start_date'] =$filters['start_date'];
        }

        if (!empty($filters['end_date'])) {$whereClauses[] = "DATE(created_at) <= :end_date";
            $params[':end_date'] =$filters['end_date'];
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $sql = "SELECT COUNT(*) FROM audit_logs {$whereSql}";
        return (int)$this->db->fetchColumn($sql,$params);
    }
}