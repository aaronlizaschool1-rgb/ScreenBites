<?php
/**
 * ScreenBites - Delete Movie Handler
 * Modeled after itim211-2b-shop/item/delete.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    mysqli_query($conn, "UPDATE movies SET is_active = 0 WHERE movie_id = {$id}");
}

header("Location: " . BASE_URL . "/movies/index.php");
exit;
