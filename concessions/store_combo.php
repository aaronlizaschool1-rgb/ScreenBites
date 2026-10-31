<?php
/**
 * ScreenBites - Store Concession Combo Handler
 * Procedural module modeled after PedalWorks-Dynamics
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['combo_name'] ?? '');
    $price = max(0.00, (float)($_POST['combo_price'] ?? 0.00));
    $desc  = trim($_POST['description'] ?? '');
    $items = (array)($_POST['items'] ?? []);

    if (empty($name) || $price <= 0.00) {
        set_flash('error', 'Bundle name and valid price are required.');
        header("Location: " . BASE_URL . "/concessions/create_combo.php");
        exit;
    }

    // Filter items with qty > 0
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
        header("Location: " . BASE_URL . "/concessions/create_combo.php");
        exit;
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $descEsc = !empty($desc) ? "'" . mysqli_real_escape_string($conn, $desc) . "'" : "NULL";

    mysqli_begin_transaction($conn);
    try {
        // 1. Insert into combos
        $sqlCombo = "INSERT INTO combos (combo_name, description, combo_price, is_active, created_at)
                     VALUES ('{$nameEsc}', {$descEsc}, {$price}, 1, NOW())";
        if (!mysqli_query($conn, $sqlCombo)) {
            throw new Exception("Error inserting combo: " . mysqli_error($conn));
        }
        $newComboId = mysqli_insert_id($conn);

        // 2. Insert into combo_items
        $sqlCi = "INSERT INTO combo_items (combo_id, product_id, quantity) VALUES (?, ?, ?)";
        $stmtCi = mysqli_prepare($conn, $sqlCi);

        foreach ($validItems as $pId => $q) {
            mysqli_stmt_bind_param($stmtCi, "iii", $newComboId, $pId, $q);
            mysqli_stmt_execute($stmtCi);
        }
        mysqli_stmt_close($stmtCi);

        mysqli_commit($conn);
        audit_log('COMBO_CREATED', 'combos', (int)$newComboId, "Created combo '{$name}' with " . count($validItems) . " constituent products at " . format_currency($price));
        set_flash('success', "Combo bundle '{$name}' was successfully created!");
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        set_flash('error', 'Failed to save combo bundle: ' . $e->getMessage());
    }

    header("Location: " . BASE_URL . "/concessions/combos.php");
    exit;
}

header("Location: " . BASE_URL . "/concessions/combos.php");
exit;
