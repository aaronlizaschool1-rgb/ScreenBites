<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use Core\Logger;
use App\Models\UtilityExpense;

class UtilityController extends Controller
{
    private UtilityExpense $expenseModel;

    public function __construct()
    {

        Auth::requireRole(ROLE_ADMIN);

        $this->expenseModel = new UtilityExpense();
    }

    public function index(): void
    {
        $selectedYear = (int)$this->get('year', (int)date('Y'));
        $selectedType = $this->get('type') ?: null;

        $expenses = $this->expenseModel->getExpenses($selectedYear, $selectedType);

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $this->view('expenses/utilities', [
            'expenses'     => $expenses,
            'selectedYear' => $selectedYear,
            'selectedType' => $selectedType,
            'utilityTypes' => UTILITY_TYPES,
            'flashSuccess' => $flashSuccess,
            'flashError'   => $flashError,
            'csrfToken'    => Auth::csrfToken()
        ]);
    }

    public function store(): void
    {
        if (!Auth::verifyCsrfToken((string)$this->post('csrf_token'))) {
            $_SESSION['flash_error'] = 'Security validation failed (Invalid CSRF token). Please try again.';
            $this->redirect('expenses/utilities');
            return;
        }

        $utilityType = trim((string)$this->post('utility_type', ''));
        $month       = (int)$this->post('billing_month', 0);
        $year        = (int)$this->post('billing_year', 0);
        $amount      = (float)$this->post('bill_amount', 0.00);
        $paymentDate = trim((string)$this->post('payment_date', ''));
        $notes       = trim((string)$this->post('notes', ''));

        if (!in_array($utilityType, UTILITY_TYPES, true)) {
            $_SESSION['flash_error'] = 'Invalid utility category selected.';
            $this->redirect('expenses/utilities');
            return;
        }

        if ($month < 1 || $month > 12 || $year < 2020) {
            $_SESSION['flash_error'] = 'Please enter a valid billing month and year.';
            $this->redirect('expenses/utilities');
            return;
        }

        if ($amount <= 0.00) {
            $_SESSION['flash_error'] = 'Utility bill amount must be greater than zero.';
            $this->redirect('expenses/utilities');
            return;
        }

        if (empty($paymentDate)) {
            $paymentDate = date('Y-m-d');
        }

        $recordedBy = Auth::id() ?? 1;

        $existing = $this->expenseModel->findByPeriod($utilityType, $year, $month);

        $result = $this->expenseModel->saveExpense(
            $utilityType,
            $month,
            $year,
            $amount,
            $paymentDate,
            $notes !== '' ? $notes : null,
            $recordedBy
        );

        $monthName = date('F', mktime(0, 0, 0, $month, 10));

        if ($result['action'] === 'updated' && $existing) {
            Logger::log(
                AUDIT_EXPENSE_MODIFIED,
                'utility_expenses',
                $result['expense_id'],
                [
                    'utility_type'    => $utilityType,
                    'period'          => "{$monthName} {$year}",
                    'previous_amount' => (float)$existing['bill_amount'],
                    'new_amount'      => $amount,
                    'notes'           => $notes
                ]
            );
            $_SESSION['flash_success'] = "Updated {$utilityType} expense for {$monthName} {$year} (₱" . number_format($amount, 2) . ").";
        } else {
            $_SESSION['flash_success'] = "Recorded {$utilityType} expense for {$monthName} {$year} (₱" . number_format($amount, 2) . ").";
        }

        $this->redirect('expenses/utilities?year=' . $year);
    }
}
