<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

class Combo
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllCombosWithItems(): array
    {
        $sql = "SELECT
                    c.combo_id,
                    c.combo_name,
                    c.description,
                    c.combo_price,
                    c.is_active,
                    c.created_at,
                    COALESCE(SUM(p.retail_price * ci.quantity), 0) AS individual_sum_price,
                    COALESCE(SUM(p.unit_cost * ci.quantity), 0) AS total_bundle_cost,
                    GROUP_CONCAT(CONCAT(ci.quantity, 'x ', p.product_name) ORDER BY p.product_name SEPARATOR ', ') AS item_breakdown
                FROM combos c
                LEFT JOIN combo_items ci ON c.combo_id = ci.combo_id
                LEFT JOIN products p ON ci.product_id = p.product_id
                WHERE c.is_active = 1
                GROUP BY c.combo_id
                ORDER BY c.combo_name ASC";

        return $this->db->fetchAll($sql);
    }

    public function findWithItems(int $comboId): ?array
    {
        $comboSql = "SELECT combo_id, combo_name, description, combo_price, is_active
                     FROM combos
                     WHERE combo_id = :id
                     LIMIT 1";

        $combo =$this->db->fetchOne($comboSql, [':id' =>$comboId]);
        if (!$combo) {
            return null;
        }

        $itemsSql = "SELECT
                        ci.product_id,
                        ci.quantity,
                        p.product_name,
                        p.category,
                        p.retail_price,
                        p.unit_cost,
                        p.current_stock
                     FROM combo_items ci
                     INNER JOIN products p ON ci.product_id = p.product_id
                     WHERE ci.combo_id = :combo_id";

        $combo['items'] =$this->db->fetchAll($itemsSql, [':combo_id' =>$comboId]);
        return $combo;
    }

    public function checkComboStockAvailability(int $comboId, int$comboQuantityRequested = 1): array
    {
        $combo = $this->findWithItems($comboId);
        if (!$combo || empty($combo['items'])) {
            return [
                'can_fulfill'   => false,
                'missing_items' => ['Combo not found or has no assigned items.']
            ];
        }

        $missing = [];
        foreach ($combo['items'] as $item) {$requiredUnits = (int)$item['quantity'] *$comboQuantityRequested;
            $currentStock  = (int)$item['current_stock'];

            if ($currentStock <$requiredUnits) {
                $missing[] = "{$item['product_name']} (Available: {$currentStock}, Needed: {$requiredUnits})";
            }
        }

        return [
            'can_fulfill'   => empty($missing),
            'missing_items' => $missing
        ];
    }

    public function createCombo(string $comboName, ?string $description, float $comboPrice, array$items): int
    {
        $this->db->beginTransaction();

        try {

            $sql = "INSERT INTO combos (combo_name, description, combo_price, is_active)
                    VALUES (:combo_name, :description, :combo_price, 1)";

            $this->db->query($sql, [
                ':combo_name'  => trim($comboName),
                ':description' => $description !== null ? trim($description) : null,
                ':combo_price' => $comboPrice
            ]);

            $comboId = (int)$this->db->lastInsertId();

            $itemSql = "INSERT INTO combo_items (combo_id, product_id, quantity)
                        VALUES (:combo_id, :product_id, :quantity)";

            foreach ($items as$item) {
                $this->db->query($itemSql, [
                    ':combo_id'   => $comboId,
                    ':product_id' => (int)$item['product_id'],
                    ':quantity'   => max(1, (int)$item['quantity'])
                ]);
            }

            $this->db->commit();

            return $comboId;
        } catch (Throwable $e) {$this->db->rollBack();
            throw $e;
        }
    }
}
