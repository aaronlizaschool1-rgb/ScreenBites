<?php
/**
 * ScreenBites - Store Utility Expense Handler
 * Procedural module modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = trim($_POST['utility_type'] ?? 'Electricity');
    $month   = max(1, min(12, (int)($_POST['billing_month'] ?? date('n'))));
    $year    = max(2020, (int)($_POST['billing_year'] ?? date('Y')));
    $amount  = max(0.01, (float)($_POST['bill_amount'] ?? 0.00));
    $status  = in_array($_POST['payment_status'] ?? '', ['Paid', 'Unpaid'], true) ? $_POST['payment_status'] : 'Paid';
    $notes   = trim($_POST['notes'] ?? '');
    $dueDate = !empty($_POST['due_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['due_date']) . "'" : "NULL";
    $payDate = (!empty($_POST['payment_date']) && $status === 'Paid') ? "'" . mysqli_real_escape_string($conn, $_POST['payment_date']) . "'" : "NULL";

    $userId = (int)(current_user()['user_id'] ?? 1);

    $typeEsc  = mysqli_real_escape_string($conn, $type);
    $notesEsc = mysqli_real_escape_string($conn, $notes);

    // Use ON DUPLICATE KEY UPDATE to gracefully handle existing bills for same period
    $sql = "INSERT INTO utility_expenses (utility_type, billing_month, billing_year, bill_amount, payment_status, due_date, payment_date, notes, recorded_by, created_at)
            VALUES ('{$typeEsc}', {$month}, {$year}, {$amount}, '{$status}', {$dueDate}, {$payDate}, '{$notesEsc}', {$userId}, NOW())
            ON DUPLICATE KEY UPDATE 
                bill_amount = VALUES(bill_amount),
                payment_status = VALUES(payment_status),
                due_date = VALUES(due_date),
                payment_date = VALUES(payment_date),
                notes = VALUES(notes),
                recorded_by = VALUES(recorded_by)";

    mysqli_query($conn, $sql);
    $insertedId = mysqli_insert_id($conn);
    audit_log('EXPENSE_MODIFIED', 'utility_expenses', (int)$insertedId, "Logged {$type} bill of " . format_currency($amount) . " for period {$month}/{$year}");

    header("Location: " . BASE_URL . "/utilities/index.php?year=" . $year);
    exit;
}

header("Location: " . BASE_URL . "/utilities/index.php");
exit;
