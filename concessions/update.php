<?php
/**
 * ScreenBites - Update Concession Item Handler
 * Modeled after itim211-2b-shop/item/update.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['product_id'] ?? 0);
    $name     = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? 'Popcorn');
    $stock    = max(0, (int)($_POST['current_stock'] ?? 0));
    $retail   = max(0.01, (float)($_POST['retail_price'] ?? 0.00));
    $cost     = max(0.00, (float)($_POST['unit_cost'] ?? 0.00));
    if ($cost > $retail) {
        $cost = $retail;
    }

    if ($id <= 0 || empty($name)) {
        header("Location: " . BASE_URL . "/concessions/index.php");
        exit;
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $catEsc  = mysqli_real_escape_string($conn, $category);

    $imgSqlPart = "";
    if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/uploads/products';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $rawName = basename($_FILES['image_file']['name']);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $rawName);
        $target = $uploadDir . '/' . $safeName;
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target)) {
            $imageRelPath = 'uploads/products/' . $safeName;
            $imgSqlPart = ", image_path = '" . mysqli_real_escape_string($conn, $imageRelPath) . "'";
        }
    }

    $sql = "UPDATE products 
            SET product_name = '{$nameEsc}', 
                category = '{$catEsc}', 
                current_stock = {$stock}, 
                retail_price = {$retail}, 
                unit_cost = {$cost}
                {$imgSqlPart}
            WHERE product_id = {$id}";

    mysqli_query($conn, $sql);
    audit_log('INVENTORY_OVERRIDE', 'products', $id, "Updated stock to {$stock} units and price to " . format_currency($retail) . " for '{$name}'");
    header("Location: " . BASE_URL . "/concessions/index.php");
    exit;
}

header("Location: " . BASE_URL . "/concessions/index.php");
exit;
