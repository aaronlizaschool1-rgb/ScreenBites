<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['product_id'] ?? 0);
    $name     = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? 'Popcorn');
    $stock    = (int)($_POST['current_stock'] ?? 0);
    $retail   = (float)($_POST['retail_price'] ?? 0.00);
    $cost     = (float)($_POST['unit_cost'] ?? 0.00);

    if ($id <= 0) {
        set_flash('error', 'Invalid concession product identifier specified.');
        header("Location: " . BASE_URL . "/concessions/index.php");
        exit;
    }

    $err = validate_required($name, 'Product name')
        ?? validate_in_array($category, ['Popcorn', 'Beverage', 'Snacks', 'Candy'], 'Category')
        ?? validate_numeric_range($stock, 0, 10000, 'Current stock')
        ?? validate_numeric_range($retail, 0.01, 100000, 'Retail price')
        ?? validate_numeric_range($cost, 0.00, 100000, 'Unit cost');

    if ($cost > $retail) {
        $cost = $retail;
    }

    if ($err !== null) {
        set_flash('error', $err);
        header("Location: " . BASE_URL . "/concessions/edit.php?id=" . $id);
        exit;
    }

    $oldImg = null;
    $chkRes = mysqli_query($conn, "SELECT image_path FROM products WHERE product_id = {$id} LIMIT 1");
    if ($chkRow = mysqli_fetch_assoc($chkRes)) {
        $oldImg = $chkRow['image_path'];
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $catEsc  = mysqli_real_escape_string($conn, $category);

    $imgSqlPart = "";
    if (!empty($_FILES['image_file']['name'])) {
        $uploadResult = handle_image_upload($_FILES['image_file'], 'products', $oldImg);
        if (!$uploadResult['success']) {
            set_flash('error', 'Product image upload failed: ' . $uploadResult['error']);
            header("Location: " . BASE_URL . "/concessions/edit.php?id=" . $id);
            exit;
        }
        $imageRelPath = $uploadResult['path'];
        $imgSqlPart = ", image_path = '" . mysqli_real_escape_string($conn, $imageRelPath) . "'";
    }

    $sql = "UPDATE products
            SET product_name = '{$nameEsc}',
                category = '{$catEsc}',
                current_stock = {$stock},
                retail_price = {$retail},
                unit_cost = {$cost}
                {$imgSqlPart}
            WHERE product_id = {$id}";

    if (mysqli_query($conn, $sql)) {
        audit_log('INVENTORY_OVERRIDE', 'products', $id, "Updated stock to {$stock} units and price to " . format_currency($retail) . " for '{$name}'");
        set_flash('success', "Snack '{$name}' was successfully updated.");
    } else {
        set_flash('error', 'Database error updating product: ' . mysqli_error($conn));
    }

    header("Location: " . BASE_URL . "/concessions/index.php");
    exit;
}

header("Location: " . BASE_URL . "/concessions/index.php");
exit;
