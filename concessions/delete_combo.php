<?php
/**
 * ScreenBites - Delete / Deactivate Concession Combo Handler
 * Procedural module modeled after PedalWorks-Dynamics
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Check if this combo has been ordered in order_combos
    $ocRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM order_combos WHERE combo_id = {$id}");
    $ocRow = mysqli_fetch_assoc($ocRes);
    $hasOrders = ((int)($ocRow['c'] ?? 0) > 0);

    if ($hasOrders) {
        // Soft delete (deactivate) to protect relational integrity with order_combos
        mysqli_query($conn, "UPDATE combos SET is_active = 0 WHERE combo_id = {$id}");
        audit_log('COMBO_DEACTIVATED', 'combos', $id, "Deactivated combo ID {$id} (preserved historical order integrity)");
        set_flash('success', 'Combo bundle has previous sales orders and was safely deactivated instead of deleted.');
    } else {
        // Hard delete
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "DELETE FROM combo_items WHERE combo_id = {$id}");
            mysqli_query($conn, "DELETE FROM combos WHERE combo_id = {$id}");
            mysqli_commit($conn);
            audit_log('COMBO_DELETED', 'combos', $id, "Permanently deleted combo ID {$id}");
            set_flash('success', 'Combo bundle was successfully deleted.');
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            set_flash('error', 'Error deleting combo bundle: ' . $e->getMessage());
        }
    }
}

header("Location: " . BASE_URL . "/concessions/combos.php");
exit;
