<?php
/**
 * ScreenBites - Create Movie
 * Modeled after itim211-2b-shop/item/create.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Add New Movie';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sb-page-header">
    <div>
        <h1>Add New Movie</h1>
        <p>Register a film title into the cinema box office catalog.</p>
    </div>
    <a href="<?= BASE_URL; ?>/movies/index.php" class="btn btn-secondary">&larr; Back to Movies</a>
</div>

<div class="sb-card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST" action="<?= BASE_URL; ?>/movies/store.php" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Movie Title *</label>
            <input type="text" id="title" name="title" class="form-control" required placeholder="e.g. Dune: Part Two">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="duration_minutes">Duration (Minutes) *</label>
                <input type="number" id="duration_minutes" name="duration_minutes" class="form-control" min="1" value="120" required>
            </div>

            <div class="form-group">
                <label for="rating">MTRCB Rating *</label>
                <select id="rating" name="rating" class="form-control" required>
                    <option value="G">G - General Audiences</option>
                    <option value="PG" selected>PG - Parental Guidance</option>
                    <option value="PG-13">PG-13 - Strict Parental Guidance</option>
                    <option value="R-13">R-13 - Restricted 13</option>
                    <option value="R-16">R-16 - Restricted 16</option>
                    <option value="R-18">R-18 - Restricted 18</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="poster_file">Movie Poster Image</label>
            <input type="file" id="poster_file" name="poster_file" class="form-control" accept="image/*">
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= BASE_URL; ?>/movies/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Movie</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
