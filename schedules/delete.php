<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $check = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM tickets WHERE screening_id = {$id}");
    $row = mysqli_fetch_assoc($check);
    if ((int)($row['cnt'] ?? 0) === 0) {
        mysqli_query($conn, "DELETE FROM screenings WHERE screening_id = {$id}");
    }
}

header("Location: " . BASE_URL . "/schedules/index.php");
exit;
