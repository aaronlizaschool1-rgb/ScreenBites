<?php
/**
 * ScreenBites - Edit Movie
 * Modeled after itim211-2b-shop/item/edit.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$res = mysqli_query($conn, "SELECT * FROM movies WHERE movie_id = {$id} LIMIT 1");
$movie = mysqli_fetch_assoc($res);

if (!$movie) {
    header("Location: " . BASE_URL . "/movies/index.php");
    exit;
}

$pageTitle = 'Edit ' . $movie['title'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sb-page-header">
    <div>
        <h1>Edit Movie</h1>
        <p>Update title, rating, runtime, and poster image.</p>
    </div>
    <a href="<?= BASE_URL; ?>/movies/index.php" class="btn btn-secondary">&larr; Back to Movies</a>
</div>

<div class="sb-card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST" action="<?= BASE_URL; ?>/movies/update.php" enctype="multipart/form-data">
        <input type="hidden" name="movie_id" value="<?= (int)$movie['movie_id']; ?>">

        <div class="form-group">
            <label for="title">Movie Title *</label>
            <input type="text" id="title" name="title" value="<?= sanitize($movie['title']); ?>" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="duration_minutes">Duration (Minutes) *</label>
                <input type="number" id="duration_minutes" name="duration_minutes" value="<?= (int)$movie['duration_minutes']; ?>" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label for="rating">MTRCB Rating *</label>
                <select id="rating" name="rating" class="form-control" required>
                    <?php
                    $ratings = ['G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18'];
                    foreach ($ratings as $r):
                    ?>
                        <option value="<?= $r; ?>" <?= ($movie['rating'] === $r) ? 'selected' : ''; ?>>
                            <?= $r; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="poster_file">Replace Poster Image (Optional)</label>
            <?php if (!empty($movie['poster_path'])): ?>
                <div style="margin-bottom: 0.5rem;">
                    <img src="<?= BASE_URL . '/' . $movie['poster_path']; ?>" alt="Poster" style="width: 70px; height: 100px; object-fit: cover; border-radius: 4px;">
                </div>
            <?php endif; ?>
            <input type="file" id="poster_file" name="poster_file" class="form-control" accept="image/*">
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= BASE_URL; ?>/movies/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
