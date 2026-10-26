<?php

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
                    ue.payment_status,
                    ue.due_date,
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

    public function findById(int $expenseId): ?array
    {
        $sql = "SELECT expense_id, utility_type, billing_month, billing_year, bill_amount,
                       payment_status, due_date, payment_date, notes, recorded_by, created_at
                FROM utility_expenses
                WHERE expense_id = :id
                LIMIT 1";

        return $this->db->fetchOne($sql, [':id' => $expenseId]);
    }

    public function findByPeriod(string $utilityType, int $year, int $month): ?array
    {
        $sql = "SELECT expense_id, utility_type, billing_month, billing_year, bill_amount,
                       payment_status, due_date, payment_date, notes, recorded_by
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

    public function saveExpense(
        string $utilityType,
        int $month,
        int $year,
        float $billAmount,
        ?string $paymentDate,
        ?string $notes,
        int $recordedBy,
        string $paymentStatus = 'Paid',
        ?string $dueDate = null
    ): array {
        $existing = $this->findByPeriod($utilityType, $year, $month);

        if ($existing) {

            $expenseId = (int)$existing['expense_id'];
            $sql = "UPDATE utility_expenses
                    SET bill_amount = :bill_amount,
                        payment_status = :payment_status,
                        due_date = :due_date,
                        payment_date = :payment_date,
                        notes = :notes,
                        recorded_by = :recorded_by
                    WHERE expense_id = :expense_id";

            $this->db->query($sql, [
                ':bill_amount'     => $billAmount,
                ':payment_status'  => $paymentStatus,
                ':due_date'        => $dueDate,
                ':payment_date'    => $paymentDate,
                ':notes'           => $notes,
                ':recorded_by'     => $recordedBy,
                ':expense_id'      => $expenseId
            ]);

            return ['action' => 'updated', 'expense_id' => $expenseId];
        }

        $sql = "INSERT INTO utility_expenses (
                    utility_type, billing_month, billing_year, bill_amount,
                    payment_status, due_date, payment_date, notes, recorded_by, created_at
                ) VALUES (
                    :utility_type, :billing_month, :billing_year, :bill_amount,
                    :payment_status, :due_date, :payment_date, :notes, :recorded_by, NOW()
                )";

        $this->db->query($sql, [
            ':utility_type'    => $utilityType,
            ':billing_month'   => $month,
            ':billing_year'    => $year,
            ':bill_amount'     => $billAmount,
            ':payment_status'  => $paymentStatus,
            ':due_date'        => $dueDate,
            ':payment_date'    => $paymentDate,
            ':notes'           => $notes,
            ':recorded_by'     => $recordedBy
        ]);

        return ['action' => 'created', 'expense_id' => (int)$this->db->lastInsertId()];
    }

    public function payExpense(int $expenseId, string $paymentDate, ?string $notes, int $recordedBy): bool
    {
        $existing = $this->findById($expenseId);
        if (!$existing) {
            return false;
        }

        $combinedNotes = trim(($existing['notes'] ? $existing['notes'] . ' | ' : '') . ($notes ?? ''));

        $sql = "UPDATE utility_expenses
                SET payment_status = 'Paid',
                    payment_date = :payment_date,
                    notes = :notes,
                    recorded_by = :recorded_by
                WHERE expense_id = :expense_id";

        $this->db->query($sql, [
            ':payment_date' => $paymentDate,
            ':notes'        => $combinedNotes !== '' ? $combinedNotes : null,
            ':recorded_by'  => $recordedBy,
            ':expense_id'   => $expenseId
        ]);

        return true;
    }

    public function autoGenerateMonthlyBills(int $year, ?int $upToMonth = null, int $recordedBy = 1): int
    {
        $currentYear  = (int)date('Y');
        $currentMonth = (int)date('n');

        if ($year > $currentYear) {
            return 0;
        }

        $maxMonth = ($year === $currentYear) ? ($upToMonth ?? $currentMonth) : ($upToMonth ?? 12);
        $maxMonth = min(12, max(1, $maxMonth));

        $generatedCount = 0;

        for ($m = 1; $m <= $maxMonth; $m++) {
            $isCurrentPeriod = ($year === $currentYear && $m === $currentMonth);

            $dueMonth = $m === 12 ? 1 : $m + 1;
            $dueYear  = $m === 12 ? $year + 1 : $year;
            $dueDate  = sprintf('%04d-%02d-15', $dueYear, $dueMonth);

            $paidDate = sprintf('%04d-%02d-08', $dueYear, $dueMonth);

            if (!$this->findByPeriod('Electricity', $year, $m)) {

                $baseElec = 39000.00 + (($m * 420) % 3500);
                $status   = $isCurrentPeriod ? 'Unpaid' : 'Paid';
                $payDate  = $isCurrentPeriod ? null : $paidDate;
                $notes    = 'Meralco Electric Draw - Dual laser projectors, audio amplifiers, theater illumination & central HVAC';

                $this->saveExpense(
                    'Electricity',
                    $m,
                    $year,
                    $baseElec,
                    $payDate,
                    $notes,
                    $recordedBy,
                    $status,
                    $dueDate
                );
                $generatedCount++;
            }

            if (!$this->findByPeriod('Water', $year, $m)) {

                $baseWater = 4200.00 + (($m * 110) % 550);
                $status    = $isCurrentPeriod ? 'Unpaid' : 'Paid';
                $payDate   = $isCurrentPeriod ? null : $paidDate;
                $notes     = 'Manila Water - Cinema restrooms sanitation & snack preparation wash down';

                $this->saveExpense(
                    'Water',
                    $m,
                    $year,
                    $baseWater,
                    $payDate,
                    $notes,
                    $recordedBy,
                    $status,
                    $dueDate
                );
                $generatedCount++;
            }
        }

        return $generatedCount;
    }

    public function getTotalUtilitiesForMonth(int $month, int $year): float
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
