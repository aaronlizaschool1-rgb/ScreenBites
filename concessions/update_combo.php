<?php
/**
 * ScreenBites - Update Concession Combo Handler
 * Procedural module modeled after PedalWorks-Dynamics
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['combo_id'] ?? 0);
    $name     = trim($_POST['combo_name'] ?? '');
    $price    = max(0.00, (float)($_POST['combo_price'] ?? 0.00));
    $desc     = trim($_POST['description'] ?? '');
    $isActive = (int)($_POST['is_active'] ?? 1);
    $items    = (array)($_POST['items'] ?? []);

    if ($id <= 0 || empty($name) || $price <= 0.00) {
        set_flash('error', 'Valid combo ID, name, and price are required.');
        header("Location: " . BASE_URL . "/concessions/combos.php");
        exit;
    }

    $validItems = [];
    foreach ($items as $prodId => $qty) {
        $pId = (int)$prodId;
        $q   = (int)$qty;
        if ($pId > 0 && $q > 0) {
            $validItems[$pId] = $q;
        }
    }

    if (empty($validItems)) {
        set_flash('error', 'A combo bundle must contain at least one constituent product.');
        header("Location: " . BASE_URL . "/concessions/edit_combo.php?id=" . $id);
        exit;
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $descEsc = !empty($desc) ? "'" . mysqli_real_escape_string($conn, $desc) . "'" : "NULL";

    mysqli_begin_transaction($conn);
    try {
        // 1. Update combos table
        $sqlCombo = "UPDATE combos 
                     SET combo_name = '{$nameEsc}', 
                         combo_price = {$price}, 
                         description = {$descEsc}, 
                         is_active = {$isActive} 
                     WHERE combo_id = {$id}";
        mysqli_query($conn, $sqlCombo);

        // 2. Refresh combo_items
        mysqli_query($conn, "DELETE FROM combo_items WHERE combo_id = {$id}");

        $sqlCi = "INSERT INTO combo_items (combo_id, product_id, quantity) VALUES (?, ?, ?)";
        $stmtCi = mysqli_prepare($conn, $sqlCi);

        foreach ($validItems as $pId => $q) {
            mysqli_stmt_bind_param($stmtCi, "iii", $id, $pId, $q);
            mysqli_stmt_execute($stmtCi);
        }
        mysqli_stmt_close($stmtCi);

        mysqli_commit($conn);
        audit_log('COMBO_UPDATED', 'combos', $id, "Updated combo '{$name}' with " . count($validItems) . " constituent products at " . format_currency($price));
        set_flash('success', "Combo bundle '{$name}' was successfully updated!");
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        set_flash('error', 'Failed to update combo bundle: ' . $e->getMessage());
    }

    header("Location: " . BASE_URL . "/concessions/combos.php");
    exit;
}

header("Location: " . BASE_URL . "/concessions/combos.php");
exit;
