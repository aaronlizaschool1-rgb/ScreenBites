<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class Hall
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getActiveHalls(): array
    {
        $sql = "SELECT
                    h.hall_id,
                    h.hall_name,
                    h.total_rows,
                    h.total_columns,
                    (h.total_rows * h.total_columns) AS theoretical_capacity,
                    COUNT(s.seat_id) AS active_seat_count
                FROM halls h
                LEFT JOIN seats s ON h.hall_id = s.hall_id AND s.is_active = 1
                WHERE h.is_active = 1
                GROUP BY h.hall_id
                ORDER BY h.hall_name ASC";

        return $this->db->fetchAll($sql);
    }

    public function findById(int $hallId): ?array
    {
        $sql = "SELECT hall_id, hall_name, total_rows, total_columns, is_active, created_at
                FROM halls
                WHERE hall_id = :hall_id
                LIMIT 1";

        return $this->db->fetchOne($sql, [':hall_id' =>$hallId]);
    }
}
