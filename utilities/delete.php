<?php
/**
 * ScreenBites - Delete Utility Expense Handler
 * Procedural module modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    mysqli_query($conn, "DELETE FROM utility_expenses WHERE expense_id = {$id}");
    audit_log('EXPENSE_MODIFIED', 'utility_expenses', $id, "Deleted utility expense record #{$id}");
}

header("Location: " . BASE_URL . "/utilities/index.php");
exit;
