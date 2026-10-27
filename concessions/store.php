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
    $stock    = max(0, (int)($_POST['current_stock'] ?? 0));
    $retail   = max(0.01, (float)($_POST['retail_price'] ?? 0.00));
    $cost     = max(0.00, (float)($_POST['unit_cost'] ?? 0.00));
    if ($cost > $retail) {
        $cost = $retail;
    }

    if (empty($name)) {
        header("Location: " . BASE_URL . "/concessions/create.php");
        exit;
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $catEsc  = mysqli_real_escape_string($conn, $category);

    $imageRelPath = null;
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
        }
    }
    $imgSql = $imageRelPath ? "'" . mysqli_real_escape_string($conn, $imageRelPath) . "'" : "NULL";

    $sql = "INSERT INTO products (product_name, category, unit_cost, retail_price, current_stock, reorder_level, image_path, is_active, created_at)
            VALUES ('{$nameEsc}', '{$catEsc}', {$cost}, {$retail}, {$stock}, 15, {$imgSql}, 1, NOW())";

    mysqli_query($conn, $sql);
    header("Location: " . BASE_URL . "/concessions/index.php");
    exit;
}

header("Location: " . BASE_URL . "/concessions/index.php");
exit;
