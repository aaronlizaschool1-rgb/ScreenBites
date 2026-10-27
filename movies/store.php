<?php
/**
 * ScreenBites - Store Movie Handler
 * Modeled after itim211-2b-shop/item/store.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $duration = max(1, (int)($_POST['duration_minutes'] ?? 120));
    $rating   = trim($_POST['rating'] ?? 'PG');

    if (empty($title)) {
        header("Location: " . BASE_URL . "/movies/create.php");
        exit;
    }

    $posterRelPath = null;
    if (!empty($_FILES['poster_file']['name']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/uploads/posters';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['poster_file']['name']);
        $target = $uploadDir . '/' . $filename;
        if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $target)) {
            $posterRelPath = 'uploads/posters/' . $filename;
        }
    }

    $titleEsc = mysqli_real_escape_string($conn, $title);
    $rateEsc  = mysqli_real_escape_string($conn, $rating);
    $imgSql   = $posterRelPath ? "'" . mysqli_real_escape_string($conn, $posterRelPath) . "'" : "NULL";

    $sql = "INSERT INTO movies (title, duration_minutes, rating, poster_path, is_active, created_at)
            VALUES ('{$titleEsc}', {$duration}, '{$rateEsc}', {$imgSql}, 1, NOW())";

    mysqli_query($conn, $sql);
    header("Location: " . BASE_URL . "/movies/index.php");
    exit;
}

header("Location: " . BASE_URL . "/movies/index.php");
exit;
