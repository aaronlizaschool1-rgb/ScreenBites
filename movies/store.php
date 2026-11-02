<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 0);
    $rating   = trim($_POST['rating'] ?? 'PG');
    $genre    = trim($_POST['genre'] ?? '');
    $rawTrailer = trim($_POST['trailer_url'] ?? '');
    $trailer  = get_youtube_id($rawTrailer);
    $synopsis = trim($_POST['synopsis'] ?? '');

    $err = validate_required($title, 'Movie title')
        ?? validate_numeric_range($duration, 1, 600, 'Duration in minutes')
        ?? validate_in_array($rating, ['G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18'], 'Age rating');

    if ($err !== null) {
        set_flash('error', $err);
        header("Location: " . BASE_URL . "/movies/create.php");
        exit;
    }

    $posterRelPath = null;
    if (!empty($_FILES['poster_file']['name'])) {
        $uploadResult = handle_image_upload($_FILES['poster_file'], 'posters');
        if (!$uploadResult['success']) {
            set_flash('error', 'Poster upload failed: ' . $uploadResult['error']);
            header("Location: " . BASE_URL . "/movies/create.php");
            exit;
        }
        $posterRelPath = $uploadResult['path'];
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
        $newId = (int)mysqli_insert_id($conn);

        if ($posterRelPath) {
            $stmtPrim = mysqli_prepare($conn, "INSERT INTO movie_images (movie_id, image_path, is_primary, created_at) VALUES (?, ?, 1, NOW())");
            if ($stmtPrim) {
                mysqli_stmt_bind_param($stmtPrim, "is", $newId, $posterRelPath);
                mysqli_stmt_execute($stmtPrim);
                mysqli_stmt_close($stmtPrim);
            }
        }

        if (!empty($_FILES['photos']['name'])) {
            $galleryFiles = normalize_files_array($_FILES['photos']);
            if (!empty($galleryFiles)) {
                $stmtGallery = mysqli_prepare($conn, "INSERT INTO movie_images (movie_id, image_path, is_primary, created_at) VALUES (?, ?, 0, NOW())");
                foreach ($galleryFiles as $photo) {
                    $upRes = handle_image_upload($photo, 'movies/gallery');
                    if ($upRes['success'] && !empty($upRes['path'])) {
                        $gPath = $upRes['path'];
                        mysqli_stmt_bind_param($stmtGallery, "is", $newId, $gPath);
                        mysqli_stmt_execute($stmtGallery);
                    }
                }
                if ($stmtGallery) {
                    mysqli_stmt_close($stmtGallery);
                }
            }
        }

        audit_log('MOVIE_CREATED', 'movies', $newId, "Added movie '{$title}' ({$duration}m, {$rating})");
        set_flash('success', "Movie '{$title}' was successfully registered into the catalog.");
    } else {
        set_flash('error', 'Database error registering movie: ' . mysqli_error($conn));
    }

    header("Location: " . BASE_URL . "/movies/index.php");
    exit;
}

header("Location: " . BASE_URL . "/movies/index.php");
exit;
