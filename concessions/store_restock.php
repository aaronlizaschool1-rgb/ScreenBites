<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/concessions/restock.php');
}

$productId        = (int)($_POST['product_id'] ?? 0);
$quantityAdded    = (int)($_POST['quantity_added'] ?? 0);
$purchaseCost     = (float)($_POST['purchase_cost'] ?? 0.0);
$supplierName     = trim($_POST['supplier_name'] ?? '');
$invoiceReference = trim($_POST['invoice_reference'] ?? '');
$notes            = trim($_POST['notes'] ?? '');
$receivedBy       = (int)($_SESSION['user_id'] ?? 1);

if ($productId <= 0 || $quantityAdded <= 0 || empty($supplierName) || $purchaseCost < 0) {
    $_SESSION['flash_error'] = 'Invalid stock receiving details. Product, quantity (>0), unit cost, and supplier name are required.';
    redirect(BASE_URL . '/concessions/restock.php' . ($productId > 0 ? "?product_id={$productId}" : ''));
}

$checkStmt = mysqli_prepare($conn, "SELECT product_id, product_name, current_stock FROM products WHERE product_id = ? LIMIT 1");
mysqli_stmt_bind_param($checkStmt, 'i', $productId);
mysqli_stmt_execute($checkStmt);
$prodRes = mysqli_stmt_get_result($checkStmt);
$product = mysqli_fetch_assoc($prodRes);
mysqli_stmt_close($checkStmt);

if (!$product) {
    $_SESSION['flash_error'] = 'Selected concession product was not found.';
    redirect(BASE_URL . '/concessions/restock.php');
}

$oldStock = (int)$product['current_stock'];
$newStock = $oldStock + $quantityAdded;

mysqli_begin_transaction($conn);

try {
    $restockSql = "INSERT INTO stock_restocks (product_id, quantity_added, purchase_cost, supplier_name, invoice_reference, notes, received_by)
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $restockSql);
    mysqli_stmt_bind_param($stmt, 'iidsdsi', $productId, $quantityAdded, $purchaseCost, $supplierName, $invoiceReference, $notes, $receivedBy);

    if (!mysqli_stmt_execute($stmt)) {
        $dbErr = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        $_SESSION['flash_error'] = "Failed to insert restock ledger: " . $dbErr;
        redirect(BASE_URL . '/concessions/restock.php?product_id=' . $productId);
    }
    $restockId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $updateSql = "UPDATE products SET current_stock = current_stock + ? WHERE product_id = ?";
    $upStmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($upStmt, 'ii', $quantityAdded, $productId);
    if (!mysqli_stmt_execute($upStmt)) {
        $dbErr = mysqli_stmt_error($upStmt);
        mysqli_stmt_close($upStmt);
        mysqli_rollback($conn);
        $_SESSION['flash_error'] = "Failed to update product stock: " . $dbErr;
        redirect(BASE_URL . '/concessions/restock.php?product_id=' . $productId);
    }
    mysqli_stmt_close($upStmt);

    $auditDetails = "Restocked +{$quantityAdded} units of {$product['product_name']} (Old: {$oldStock}, New: {$newStock}). Supplier: {$supplierName}, Ref: {$invoiceReference}, Unit Cost: ₱" . number_format($purchaseCost, 2);
    audit_log('STOCK_RESTOCK', 'products', $productId, $auditDetails);

    mysqli_commit($conn);

    $_SESSION['flash_success'] = "Successfully added +{$quantityAdded} units to {$product['product_name']}. New on-hand stock: {$newStock}.";
    redirect(BASE_URL . '/concessions/restock_history.php');

} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['flash_error'] = "Stock receiving error: " . (mysqli_error($conn) ?: 'Internal database error.');
    redirect(BASE_URL . '/concessions/restock.php?product_id=' . $productId);
}
