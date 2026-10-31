<?php
/**
 * ScreenBites - Update Movie Handler
 * Modeled after PedalWorks-Dynamics procedural architecture
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['movie_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $duration = max(1, (int)($_POST['duration_minutes'] ?? 120));
    $rating   = trim($_POST['rating'] ?? 'PG');
    $genre    = trim($_POST['genre'] ?? '');
    $rawTrailer = trim($_POST['trailer_url'] ?? '');
    $trailer  = get_youtube_id($rawTrailer);
    $synopsis = trim($_POST['synopsis'] ?? '');

    if ($id <= 0 || empty($title)) {
        set_flash('error', 'Invalid movie record or empty title.');
        header("Location: " . BASE_URL . "/movies/index.php");
        exit;
    }

    $titleEsc    = mysqli_real_escape_string($conn, $title);
    $rateEsc     = mysqli_real_escape_string($conn, $rating);
    $genreEsc    = !empty($genre) ? "'" . mysqli_real_escape_string($conn, $genre) . "'" : "NULL";
    $trailerEsc  = !empty($trailer) ? "'" . mysqli_real_escape_string($conn, $trailer) . "'" : "NULL";
    $synopsisEsc = !empty($synopsis) ? "'" . mysqli_real_escape_string($conn, $synopsis) . "'" : "NULL";

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
                rating = '{$rateEsc}',
                genre = {$genreEsc},
                trailer_url = {$trailerEsc},
                synopsis = {$synopsisEsc}
                {$imgSqlPart}
            WHERE movie_id = {$id}";

    if (mysqli_query($conn, $sql)) {
        audit_log('MOVIE_UPDATED', 'movies', $id, "Updated movie '{$title}' ({$duration}m, {$rating})");
        set_flash('success', "Movie '{$title}' was successfully updated.");
    } else {
        set_flash('error', 'Database error updating movie: ' . mysqli_error($conn));
    }

    header("Location: " . BASE_URL . "/movies/index.php");
    exit;
}

header("Location: " . BASE_URL . "/movies/index.php");
exit;
