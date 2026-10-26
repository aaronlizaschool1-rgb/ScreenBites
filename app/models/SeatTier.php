<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class SeatTier
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllTiers(): array
    {
        $sql = "SELECT tier_id, tier_name, tier_surcharge, created_at
                FROM seat_tiers
                ORDER BY tier_surcharge ASC";

        return $this->db->fetchAll($sql);
    }

    public function findById(int $tierId): ?array
    {
        $sql = "SELECT tier_id, tier_name, tier_surcharge
                FROM seat_tiers
                WHERE tier_id = :tier_id
                LIMIT 1";

        return $this->db->fetchOne($sql, [':tier_id' => $tierId]);
    }
}
