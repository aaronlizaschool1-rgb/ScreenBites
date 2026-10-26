<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class Product
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllProducts(): array
    {
        $sql = "SELECT
                    product_id,
                    product_name,
                    category,
                    unit_cost,
                    retail_price,
                    current_stock,
                    reorder_level,
                    is_active,
                    (current_stock <= reorder_level) AS is_low_stock,
                    created_at,
                    updated_at
                FROM products
                WHERE is_active = 1
                ORDER BY category ASC, product_name ASC";

        return $this->db->fetchAll($sql);
    }

    public function findById(int $productId): ?array
    {
        $sql = "SELECT
                    product_id,
                    product_name,
                    category,
                    unit_cost,
                    retail_price,
                    current_stock,
                    reorder_level,
                    is_active
                FROM products
                WHERE product_id = :id
                LIMIT 1";

        return $this->db->fetchOne($sql, [':id' => $productId]);
    }

    public function getLowStockAlerts(): array
    {
        $sql = "SELECT
                    product_id,
                    product_name,
                    category,
                    current_stock,
                    reorder_level,
                    (reorder_level - current_stock) AS units_deficit
                FROM products
                WHERE is_active = 1
                  AND current_stock <= reorder_level
                ORDER BY current_stock ASC";

        return $this->db->fetchAll($sql);
    }

    public function decrementStock(int $productId, int $quantity): bool
    {
        $sql = "UPDATE products
                SET current_stock = current_stock - :qty
                WHERE product_id = :id
                  AND current_stock >= :min_qty";

        return $this->db->execute($sql, [
            ':qty'     => $quantity,
            ':id'      => $productId,
            ':min_qty' => $quantity
        ]) > 0;
    }

    public function incrementStock(int $productId, int $quantity): bool
    {
        $sql = "UPDATE products
                SET current_stock = current_stock + :qty
                WHERE product_id = :id";

        return $this->db->execute($sql, [
            ':qty' => $quantity,
            ':id'  => $productId
        ]) > 0;
    }

    public function updateStock(int $productId, int $newStock): bool
    {
        $sql = "UPDATE products
                SET current_stock = :new_stock
                WHERE product_id = :id";

        return $this->db->execute($sql, [
            ':new_stock' => $newStock,
            ':id'        => $productId
        ]) > 0;
    }

    public function create(
        string $name,
        string $category,
        float $unitCost,
        float $retailPrice,
        int $initialStock,
        int $reorderLevel = 15
    ): int {
        $sql = "INSERT INTO products (
                    product_name, category, unit_cost, retail_price, current_stock, reorder_level, is_active
                ) VALUES (
                    :name, :category, :unit_cost, :retail_price, :current_stock, :reorder_level, 1
                )";

        $this->db->query($sql, [
            ':name'          => trim($name),
            ':category'      => $category,
            ':unit_cost'     => $unitCost,
            ':retail_price'  => $retailPrice,
            ':current_stock' => $initialStock,
            ':reorder_level' => $reorderLevel
        ]);

        return (int)$this->db->lastInsertId();
    }
}
