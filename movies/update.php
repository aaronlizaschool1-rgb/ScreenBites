<?php
/**
 * ScreenBites - Update Movie Handler
 * Modeled after itim211-2b-shop/item/update.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['movie_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $duration = max(1, (int)($_POST['duration_minutes'] ?? 120));
    $rating   = trim($_POST['rating'] ?? 'PG');

    if ($id <= 0 || empty($title)) {
        header("Location: " . BASE_URL . "/movies/index.php");
        exit;
    }

    $titleEsc = mysqli_real_escape_string($conn, $title);
    $rateEsc  = mysqli_real_escape_string($conn, $rating);

    $imgSqlPart = "";
    if (!empty($_FILES['poster_file']['name']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/uploads/posters';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['poster_file']['name']);
        $target = $uploadDir . '/' . $filename;
        if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $target)) {
            $posterRelPath = 'uploads/posters/' . $filename;
            $imgSqlPart = ", poster_path = '" . mysqli_real_escape_string($conn, $posterRelPath) . "'";
        }
    }

    $sql = "UPDATE movies 
            SET title = '{$titleEsc}', 
                duration_minutes = {$duration}, 
                rating = '{$rateEsc}'
                {$imgSqlPart}
            WHERE movie_id = {$id}";

    mysqli_query($conn, $sql);
    header("Location: " . BASE_URL . "/movies/index.php");
    exit;
}

header("Location: " . BASE_URL . "/movies/index.php");
exit;
