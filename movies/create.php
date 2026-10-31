<?php
/**
 * ScreenBites - Create Movie
 * Modeled after PedalWorks-Dynamics procedural architecture
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Register Movie Title';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sb-page-header">
    <div>
        <h1>Register New Movie Title</h1>
        <p>Add a cinematic title, age rating, official YouTube trailer, and synopsis to the catalog.</p>
    </div>
    <a href="<?= BASE_URL; ?>/movies/index.php" class="btn btn-secondary">&larr; Back to Catalog</a>
</div>

<div class="sb-card" style="max-width: 680px; margin: 0 auto;">
    <form method="POST" action="<?= BASE_URL; ?>/movies/store.php" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Movie Title *</label>
            <input type="text" id="title" name="title" class="form-control" required placeholder="e.g. Gladiator II">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="duration_minutes">Runtime (Minutes) *</label>
                <input type="number" id="duration_minutes" name="duration_minutes" class="form-control" min="1" max="600" value="120" required>
            </div>

            <div class="form-group">
                <label for="rating">MTRCB Age Rating *</label>
                <select id="rating" name="rating" class="form-control" required>
                    <option value="G">G - General Audiences</option>
                    <option value="PG" selected>PG - Parental Guidance</option>
                    <option value="PG-13">PG-13 - Strictly 13 &amp; Above</option>
                    <option value="R-13">R-13 - Restricted 13</option>
                    <option value="R-16">R-16 - Restricted 16</option>
                    <option value="R-18">R-18 - Adults Only 18</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="genre">Genre(s)</label>
                <input type="text" id="genre" name="genre" class="form-control" placeholder="e.g. Action / Sci-Fi / Adventure">
            </div>

            <div class="form-group">
                <label for="trailer_url">YouTube Trailer ID or URL</label>
                <input type="text" id="trailer_url" name="trailer_url" class="form-control" placeholder="e.g. d9MyW72ELq0 or full YouTube URL">
            </div>
        </div>

        <div class="form-group">
            <label for="synopsis">Official Synopsis / Storyline</label>
            <textarea id="synopsis" name="synopsis" class="form-control" rows="3" placeholder="Brief cinematic synopsis of the movie..."></textarea>
        </div>

        <div class="form-group">
            <label for="poster_file">Movie Poster Artwork Image (JPG, PNG, WebP)</label>
            <input type="file" id="poster_file" name="poster_file" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= BASE_URL; ?>/movies/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save to Now Showing</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
