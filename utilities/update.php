<?php
/**
 * ScreenBites - Update Utility Expense Handler
 * Procedural module modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['expense_id'] ?? 0);
    $type    = trim($_POST['utility_type'] ?? 'Electricity');
    $month   = max(1, min(12, (int)($_POST['billing_month'] ?? date('n'))));
    $year    = max(2020, (int)($_POST['billing_year'] ?? date('Y')));
    $amount  = max(0.01, (float)($_POST['bill_amount'] ?? 0.00));
    $status  = in_array($_POST['payment_status'] ?? '', ['Paid', 'Unpaid'], true) ? $_POST['payment_status'] : 'Paid';
    $notes   = trim($_POST['notes'] ?? '');
    $dueDate = !empty($_POST['due_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['due_date']) . "'" : "NULL";
    $payDate = (!empty($_POST['payment_date']) && $status === 'Paid') ? "'" . mysqli_real_escape_string($conn, $_POST['payment_date']) . "'" : "NULL";

    if ($id <= 0) {
        header("Location: " . BASE_URL . "/utilities/index.php");
        exit;
    }

    $typeEsc  = mysqli_real_escape_string($conn, $type);
    $notesEsc = mysqli_real_escape_string($conn, $notes);

    $sql = "UPDATE utility_expenses 
            SET utility_type = '{$typeEsc}', 
                billing_month = {$month}, 
                billing_year = {$year}, 
                bill_amount = {$amount}, 
                payment_status = '{$status}', 
                due_date = {$dueDate}, 
                payment_date = {$payDate}, 
                notes = '{$notesEsc}'
            WHERE expense_id = {$id}";

    mysqli_query($conn, $sql);
    audit_log('EXPENSE_MODIFIED', 'utility_expenses', $id, "Updated {$type} bill to " . format_currency($amount) . " (Status: {$status}) for period {$month}/{$year}");

    header("Location: " . BASE_URL . "/utilities/index.php?year=" . $year);
    exit;
}

header("Location: " . BASE_URL . "/utilities/index.php");
exit;
