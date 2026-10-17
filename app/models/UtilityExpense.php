<?php
// Utility expense model<?php
/**
 * ScreenBites: Utility Expense Model
 * 
 * Manages fixed and variable overhead expenses (Electricity & Water)
 * for the `utility_expenses` table (FR11, FR12).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class UtilityExpense
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Retrieve logged utility expenses filtered by optional year and utility type
     * 
     * @param int|null $year
     * @param string|null $utilityType ENUM('Electricity', 'Water')
     * @return array<int, array<string, mixed>>
     */
    public function getExpenses(?int $year = null, ?string $utilityType = null): array
    {
        $params = [];$whereClauses = [];

        if ($year !== null && $year > 0) {$whereClauses[] = "ue.billing_year = :year";
            $params[':year'] =$year;
        }

        if ($utilityType !== null && in_array($utilityType, UTILITY_TYPES, true)) {$whereClauses[] = "ue.utility_type = :utility_type";
            $params[':utility_type'] =$utilityType;
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $sql = "SELECT 
                    ue.expense_id,
                    ue.utility_type,
                    ue.billing_month,
                    ue.billing_year,
                    ue.bill_amount,
                    ue.payment_date,
                    ue.notes,
                    ue.recorded_by,
                    ue.created_at,
                    u.full_name AS recorded_by_name
                FROM utility_expenses ue
                INNER JOIN users u ON ue.recorded_by = u.user_id
                {$whereSql}
                ORDER BY ue.billing_year DESC, ue.billing_month DESC, ue.utility_type ASC";

        return $this->db->fetchAll($sql,$params);
    }

    /**
     * Find a specific utility expense record by its ID
     * 
     * @param int $expenseId
     * @return array<string, mixed>|null
     */
    public function findById(int $expenseId): ?array
    {
        $sql = "SELECT expense_id, utility_type, billing_month, billing_year, bill_amount, 
                       payment_date, notes, recorded_by, created_at
                FROM utility_expenses
                WHERE expense_id = :id
                LIMIT 1";

        return $this->db->fetchOne($sql, [':id' =>$expenseId]);
    }

    /**
     * Find an expense by its unique period key (FR11 3NF constraint)
     * 
     * @param string $utilityType
     * @param int $year
     * @param int $month
     * @return array<string, mixed>|null
     */
    public function findByPeriod(string $utilityType, int $year, int$month): ?array
    {
        $sql = "SELECT expense_id, utility_type, billing_month, billing_year, bill_amount, 
                       payment_date, notes, recorded_by
                FROM utility_expenses
                WHERE utility_type = :utility_type 
                  AND billing_year = :year 
                  AND billing_month = :month
                LIMIT 1";

        return $this->db->fetchOne($sql, [
            ':utility_type' => $utilityType,
            ':year'         => $year,
            ':month'        => $month
        ]);
    }

    /**
     * Insert or update an expense entry atomically (Upsert)
     * 
     * @param string $utilityType
     * @param int $month
     * @param int $year
     * @param float $billAmount
     * @param string $paymentDate
     * @param string|null $notes
     * @param int $recordedBy
     * @return array{action: string, expense_id: int}
     */
    public function saveExpense(
        string $utilityType,
        int $month,
        int $year,
        float $billAmount,
        string $paymentDate,
        ?string $notes,
        int $recordedBy
    ): array {
        $existing = $this->findByPeriod($utilityType, $year,$month);

        if ($existing) {
            // Update existing entry
            $expenseId = (int)$existing['expense_id'];$sql = "UPDATE utility_expenses 
                    SET bill_amount = :bill_amount,
                        payment_date = :payment_date,
                        notes = :notes,
                        recorded_by = :recorded_by
                    WHERE expense_id = :expense_id";

            $this->db->query($sql, [
                ':bill_amount'  => $billAmount,
                ':payment_date' => $paymentDate,
                ':notes'        => $notes,
                ':recorded_by'  => $recordedBy,
                ':expense_id'   => $expenseId
            ]);

            return ['action' => 'updated', 'expense_id' => $expenseId];
        }

        // Insert fresh entry
        $sql = "INSERT INTO utility_expenses (
                    utility_type, billing_month, billing_year, bill_amount, payment_date, notes, recorded_by, created_at
                ) VALUES (
                    :utility_type, :billing_month, :billing_year, :bill_amount, :payment_date, :notes, :recorded_by, NOW()
                )";

        $this->db->query($sql, [
            ':utility_type'   => $utilityType,
            ':billing_month'  => $month,
            ':billing_year'   => $year,
            ':bill_amount'    => $billAmount,
            ':payment_date'   => $paymentDate,
            ':notes'          => $notes,
            ':recorded_by'    => $recordedBy
        ]);

        return ['action' => 'created', 'expense_id' => (int)$this->db->lastInsertId()];
    }

    /**
     * Calculate total utility overhead for a given billing month and year (FR12 helper)
     * 
     * @param int $month
     * @param int $year
     * @return float
     */
    public function getTotalUtilitiesForMonth(int $month, int$year): float
    {
        $sql = "SELECT COALESCE(SUM(bill_amount), 0.00) 
                FROM utility_expenses
                WHERE billing_month = :month AND billing_year = :year";

        return (float)$this->db->fetchColumn($sql, [
            ':month' => $month,
            ':year'  => $year
        ]);
    }
}