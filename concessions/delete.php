<?php
/**
 * ScreenBites - Delete Concession Item
 * Modeled after itim211-2b-shop/item/delete.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    mysqli_query($conn, "UPDATE products SET is_active = 0 WHERE product_id = {$id}");
}

header("Location: " . BASE_URL . "/concessions/index.php");
exit;
