<?php
/**
 * ScreenBites: Payment Model
 * 
 * Handles multi-method payment transactions, split tenders,
 * and cash change calculations (FR9).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class Payment
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Record a payment tender against an order
     * 
     * @param int $orderId
     * @param string $method ENUM('Cash', 'E-Wallet', 'Card')
     * @param float $amountPaid Amount collected
     * @param float $changeReturned Calculated change (for cash tenders)
     * @param string|null $referenceNumber External txn ref (for GCash/Maya/Card)
     * @return int Inserted payment_id
     */
    public function recordPayment(
        int $orderId,
        string $method,
        float $amountPaid,
        float $changeReturned = 0.00,
        ?string $referenceNumber = null
    ): int {
        $sql = "INSERT INTO payments (order_id, payment_method, amount_paid, change_returned, reference_number, payment_date)
                VALUES (:order_id, :payment_method, :amount_paid, :change_returned, :reference_number, NOW())";

        $this->db->query($sql, [
            ':order_id'         => $orderId,
            ':payment_method'   => $method,
            ':amount_paid'      => $amountPaid,
            ':change_returned'  => max(0.00, $changeReturned),
            ':reference_number' => $referenceNumber
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Retrieve all payment records associated with an order
     */
    public function getPaymentsByOrderId(int $orderId): array
    {
        $sql = "SELECT payment_id, order_id, payment_method, amount_paid, change_returned, reference_number, payment_date
                FROM payments
                WHERE order_id = :order_id
                ORDER BY payment_id ASC";

        return $this->db->fetchAll($sql, [':order_id' => $orderId]);
    }
}