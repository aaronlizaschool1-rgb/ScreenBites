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
    $duration = (int)($_POST['duration_minutes'] ?? 0);
    $rating   = trim($_POST['rating'] ?? 'PG');
    $genre    = trim($_POST['genre'] ?? '');
    $rawTrailer = trim($_POST['trailer_url'] ?? '');
    $trailer  = get_youtube_id($rawTrailer);
    $synopsis = trim($_POST['synopsis'] ?? '');

    if ($id <= 0) {
        set_flash('error', 'Invalid movie identifier specified.');
        header("Location: " . BASE_URL . "/movies/index.php");
        exit;
    }

    // Procedural validation
    $err = validate_required($title, 'Movie title')
        ?? validate_numeric_range($duration, 1, 600, 'Duration in minutes')
        ?? validate_in_array($rating, ['G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18'], 'Age rating');

    if ($err !== null) {
        set_flash('error', $err);
        header("Location: " . BASE_URL . "/movies/edit.php?id=" . $id);
        exit;
    }

    // Retrieve old poster for clean deletion if replaced
    $oldPoster = null;
    $chkRes = mysqli_query($conn, "SELECT poster_path FROM movies WHERE movie_id = {$id} LIMIT 1");
    if ($chkRow = mysqli_fetch_assoc($chkRes)) {
        $oldPoster = $chkRow['poster_path'];
    }

    $posterRelPath = null;
    $imgSqlPart = "";
    if (!empty($_FILES['poster_file']['name'])) {
        $uploadResult = handle_image_upload($_FILES['poster_file'], 'posters', $oldPoster);
        if (!$uploadResult['success']) {
            set_flash('error', 'Poster upload failed: ' . $uploadResult['error']);
            header("Location: " . BASE_URL . "/movies/edit.php?id=" . $id);
            exit;
        }
        $posterRelPath = $uploadResult['path'];
        $imgSqlPart = ", poster_path = '" . mysqli_real_escape_string($conn, $posterRelPath) . "'";
    }

    // Delete requested gallery images
    if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        $delStmt = mysqli_prepare($conn, "SELECT image_path FROM movie_images WHERE image_id = ? AND movie_id = ?");
        $delExec = mysqli_prepare($conn, "DELETE FROM movie_images WHERE image_id = ? AND movie_id = ?");
        foreach ($_POST['delete_images'] as $delImgId) {
            $dId = (int)$delImgId;
            mysqli_stmt_bind_param($delStmt, "ii", $dId, $id);
            mysqli_stmt_execute($delStmt);
            $dRes = mysqli_stmt_get_result($delStmt);
            if ($dRow = mysqli_fetch_assoc($dRes)) {
                $absP = dirname(__DIR__) . '/' . ltrim($dRow['image_path'], '/\\');
                if (file_exists($absP) && is_file($absP)) {
                    @unlink($absP);
                }
                mysqli_stmt_bind_param($delExec, "ii", $dId, $id);
                mysqli_stmt_execute($delExec);
            }
        }
        mysqli_stmt_close($delStmt);
        mysqli_stmt_close($delExec);
    }

    // Process new gallery stills uploads (MP1 upgrade)
    if (!empty($_FILES['photos']['name'])) {
        $galleryFiles = normalize_files_array($_FILES['photos']);
        if (!empty($galleryFiles)) {
            $stmtGallery = mysqli_prepare($conn, "INSERT INTO movie_images (movie_id, image_path, is_primary, created_at) VALUES (?, ?, 0, NOW())");
            foreach ($galleryFiles as $photo) {
                $upRes = handle_image_upload($photo, 'movies/gallery');
                if ($upRes['success'] && !empty($upRes['path'])) {
                    $gPath = $upRes['path'];
                    mysqli_stmt_bind_param($stmtGallery, "is", $id, $gPath);
                    mysqli_stmt_execute($stmtGallery);
                }
            }
            if ($stmtGallery) {
                mysqli_stmt_close($stmtGallery);
            }
        }
    }

    // Update primary poster in movie_images if replaced
    if ($posterRelPath) {
        $delPrim = mysqli_prepare($conn, "DELETE FROM movie_images WHERE movie_id = ? AND is_primary = 1");
        mysqli_stmt_bind_param($delPrim, "i", $id);
        mysqli_stmt_execute($delPrim);
        mysqli_stmt_close($delPrim);

        $insPrim = mysqli_prepare($conn, "INSERT INTO movie_images (movie_id, image_path, is_primary, created_at) VALUES (?, ?, 1, NOW())");
        mysqli_stmt_bind_param($insPrim, "is", $id, $posterRelPath);
        mysqli_stmt_execute($insPrim);
        mysqli_stmt_close($insPrim);
    }

    $titleEsc    = mysqli_real_escape_string($conn, $title);
    $rateEsc     = mysqli_real_escape_string($conn, $rating);
    $genreEsc    = !empty($genre) ? "'" . mysqli_real_escape_string($conn, $genre) . "'" : "NULL";
    $trailerEsc  = !empty($trailer) ? "'" . mysqli_real_escape_string($conn, $trailer) . "'" : "NULL";
    $synopsisEsc = !empty($synopsis) ? "'" . mysqli_real_escape_string($conn, $synopsis) . "'" : "NULL";

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
