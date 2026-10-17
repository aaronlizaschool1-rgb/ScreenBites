<?php
/**
 * ScreenBites: Order Model
 * 
 * Manages parent sales orders and itemized concession lines (FR8, FR9).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class Order
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate unique daily order receipt number (e.g., ORD-20261017-XXXX)
     */
    public function generateOrderNumber(): string
    {
        $prefix = 'ORD-' . date('Ymd') . '-';
        $random = strtoupper(bin2hex(random_bytes(2)));
        return $prefix . $random;
    }

    /**
     * Insert a parent order record
     * 
     * @param string $orderNumber
     * @param int $userId Cashier / Staff processing the order
     * @param float $subtotal Total before tax
     * @param float $taxAmount 12% VAT portion
     * @return int Inserted order_id
     */
    public function createOrder(string $orderNumber, int $userId, float $subtotal, float $taxAmount): int
    {
        $sql = "INSERT INTO orders (order_number, user_id, subtotal_amount, tax_amount, order_status, created_at)
                VALUES (:order_number, :user_id, :subtotal, :tax_amount, 'Completed', NOW())";

        $this->db->query($sql, [
            ':order_number' => $orderNumber,
            ':user_id'      => $userId,
            ':subtotal'     => $subtotal,
            ':tax_amount'   => $taxAmount
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Insert an itemized concession line item into order_items
     * 
     * @param int $orderId
     * @param int|null $productId
     * @param int|null $comboId
     * @param int $quantity
     * @param float $unitPrice
     */
    public function addOrderItem(
        int $orderId,
        ?int $productId,
        ?int $comboId,
        int $quantity,
        float $unitPrice
    ): void {
        $sql = "INSERT INTO order_items (order_id, product_id, combo_id, quantity, unit_price_charged)
                VALUES (:order_id, :product_id, :combo_id, :quantity, :unit_price)";

        $this->db->query($sql, [
            ':order_id'    => $orderId,
            ':product_id'  => $productId,
            ':combo_id'    => $comboId,
            ':quantity'    => $quantity,
            ':unit_price'  => $unitPrice
        ]);
    }

    /**
     * Find an order by order number including user details
     */
    public function findByOrderNumber(string $orderNumber): ?array
    {
        $sql = "SELECT o.order_id, o.order_number, o.user_id, o.subtotal_amount, o.tax_amount,
                       (o.subtotal_amount + o.tax_amount) AS total_amount,
                       o.order_status, o.created_at, u.full_name AS cashier_name
                FROM orders o
                INNER JOIN users u ON o.user_id = u.user_id
                WHERE o.order_number = :order_number
                LIMIT 1";

        return $this->db->fetchOne($sql, [':order_number' => $orderNumber]);
    }

    /**
     * Retrieve all line items for an order (both standalone products and combos)
     */
    public function getOrderItems(int $orderId): array
    {
        $sql = "SELECT oi.order_item_id, oi.product_id, oi.combo_id, oi.quantity, oi.unit_price_charged,
                       (oi.quantity * oi.unit_price_charged) AS total_line_price,
                       COALESCE(p.product_name, c.combo_name) AS item_name,
                       CASE WHEN oi.combo_id IS NOT NULL THEN 'Bundle' ELSE p.category END AS category
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.product_id
                LEFT JOIN combos c ON oi.combo_id = c.combo_id
                WHERE oi.order_id = :order_id";

        return $this->db->fetchAll($sql, [':order_id' => $orderId]);
    }
}