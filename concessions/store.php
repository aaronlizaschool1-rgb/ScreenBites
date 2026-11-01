<?php
/**
 * ScreenBites - Store Concession Product Handler
 * Modeled after itim211-2b-shop/item/store.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? 'Popcorn');
    $stock    = (int)($_POST['current_stock'] ?? 0);
    $retail   = (float)($_POST['retail_price'] ?? 0.00);
    $cost     = (float)($_POST['unit_cost'] ?? 0.00);

    // Procedural validation
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
        header("Location: " . BASE_URL . "/concessions/create.php");
        exit;
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $catEsc  = mysqli_real_escape_string($conn, $category);

    $imageRelPath = null;
    if (!empty($_FILES['image_file']['name'])) {
        $uploadResult = handle_image_upload($_FILES['image_file'], 'products');
        if (!$uploadResult['success']) {
            set_flash('error', 'Product image upload failed: ' . $uploadResult['error']);
            header("Location: " . BASE_URL . "/concessions/create.php");
            exit;
        }
        $imageRelPath = $uploadResult['path'];
    }
    $imgSql = $imageRelPath ? "'" . mysqli_real_escape_string($conn, $imageRelPath) . "'" : "NULL";

    $sql = "INSERT INTO products (product_name, category, unit_cost, retail_price, current_stock, reorder_level, image_path, is_active, created_at)
            VALUES ('{$nameEsc}', '{$catEsc}', {$cost}, {$retail}, {$stock}, 15, {$imgSql}, 1, NOW())";

    if (mysqli_query($conn, $sql)) {
        $newId = mysqli_insert_id($conn);
        audit_log('PRODUCT_CREATED', 'products', (int)$newId, "Created snack '{$name}' (Stock: {$stock}, Price: " . format_currency($retail) . ")");
        set_flash('success', "Snack '{$name}' was successfully added to inventory.");
    } else {
        set_flash('error', 'Database error registering snack product: ' . mysqli_error($conn));
    }

    header("Location: " . BASE_URL . "/concessions/index.php");
    exit;
}

header("Location: " . BASE_URL . "/concessions/index.php");
exit;
