<?php
/**
 * ScreenBites - Store Movie Handler
 * Modeled after PedalWorks-Dynamics procedural architecture
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $duration = max(1, (int)($_POST['duration_minutes'] ?? 120));
    $rating   = trim($_POST['rating'] ?? 'PG');
    $genre    = trim($_POST['genre'] ?? '');
    $rawTrailer = trim($_POST['trailer_url'] ?? '');
    $trailer  = get_youtube_id($rawTrailer);
    $synopsis = trim($_POST['synopsis'] ?? '');

    if (empty($title)) {
        set_flash('error', 'Movie title cannot be empty.');
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

    $titleEsc    = mysqli_real_escape_string($conn, $title);
    $rateEsc     = mysqli_real_escape_string($conn, $rating);
    $genreEsc    = !empty($genre) ? "'" . mysqli_real_escape_string($conn, $genre) . "'" : "NULL";
    $trailerEsc  = !empty($trailer) ? "'" . mysqli_real_escape_string($conn, $trailer) . "'" : "NULL";
    $synopsisEsc = !empty($synopsis) ? "'" . mysqli_real_escape_string($conn, $synopsis) . "'" : "NULL";
    $imgSql      = $posterRelPath ? "'" . mysqli_real_escape_string($conn, $posterRelPath) . "'" : "NULL";

    $sql = "INSERT INTO movies (title, duration_minutes, rating, poster_path, trailer_url, genre, synopsis, is_active, created_at)
            VALUES ('{$titleEsc}', {$duration}, '{$rateEsc}', {$imgSql}, {$trailerEsc}, {$genreEsc}, {$synopsisEsc}, 1, NOW())";

    if (mysqli_query($conn, $sql)) {
        $newId = mysqli_insert_id($conn);
        audit_log('MOVIE_CREATED', 'movies', (int)$newId, "Added movie '{$title}' ({$duration}m, {$rating})");
        set_flash('success', "Movie '{$title}' was successfully registered into the catalog.");
    } else {
        set_flash('error', 'Database error registering movie: ' . mysqli_error($conn));
    }

    header("Location: " . BASE_URL . "/movies/index.php");
    exit;
}

header("Location: " . BASE_URL . "/movies/index.php");
exit;
