<?php
/**
 * ScreenBites: Movie Catalog Management View
 * 
 * Lists all active movie titles, durations, age ratings, and posters.
 * Provides the modal interface to register new cinema titles (FR1).
 * Restricted to Administrators (FR13).
 * 
 * @var array<int, array<string, mixed>> $movies
 * @var string|null                      $flashSuccess
 * @var string|null                      $flashError
 * @var string                           $csrfToken
 */

$e = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$movies = is_array($movies ?? null) ? $movies : [];

$durationText = function ($mins): string {
    $mins = (int)$mins;
    return $mins >= 60 ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm' : $mins . 'm';
};

$totalMovies = count($movies);
$familyRatedCount = 0;
$restrictedCount = 0;

foreach ($movies as $m) {
    $r = (string)($m['rating'] ?? 'PG');
    if (in_array($r, ['G', 'PG'], true)) {
        $familyRatedCount++;
    } else {
        $restrictedCount++;
    }
}
?>

<div class="page-head">
    <div>
        <h1>Movie Titles &amp; Catalog</h1>
        <p>Manage cinema movie titles, runtime durations, age classifications, and poster artwork (FR1).</p>
    </div>
    <div class="movie-head-actions">
        <button type="button" class="btn" id="openAddMovieBtn">
            + Add New Movie
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi">
        <p class="kpi-label">Active Cinema Titles</p>
        <p class="kpi-value"><?= $totalMovies; ?></p>
        <p class="kpi-note">Available for hall scheduling</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">General &amp; Parental Guidance</p>
        <p class="kpi-value is-positive"><?= $familyRatedCount; ?></p>
        <p class="kpi-note">G and PG rated titles</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Teens &amp; Mature Restricted</p>
        <p class="kpi-value"><?= $restrictedCount; ?></p>
        <p class="kpi-note">PG-13, R-13, R-16, and R-18</p>
    </div>
</div>

<!-- Movie Catalog Grid -->
<div class="sched-list" id="moviesList">
    <?php if (empty($movies)): ?>
        <div class="card" style="grid-column: 1 / -1;">
            <p class="empty-note">No movies found in the catalog. Click "+ Add New Movie" to register one.</p>
        </div>
    <?php else: ?>
        <?php foreach ($movies as $movie): 
            $mId = (int)$movie['movie_id'];
            $title = (string)$movie['title'];
            $poster = !empty($movie['poster_path']) ? BASE_URL . '/' . ltrim((string)$movie['poster_path'], '/') : null;
            $rating = (string)($movie['rating'] ?? 'PG');
            $runtime = (int)$movie['duration_minutes'];
        ?>
            <article class="sched-card">
                <div class="sched-poster" aria-hidden="true">
                    <?php if ($poster): ?>
                        <img src="<?= $e($poster); ?>" alt="<?= $e($title); ?> poster" loading="lazy">
                    <?php else: ?>
                        <?= $e(mb_strtoupper(mb_substr($title, 0, 1))); ?>
                    <?php endif; ?>
                </div>

                <div class="sched-body">
                    <h3><?= $e($title); ?></h3>
                    
                    <p class="sched-meta">
                        <span class="rating-badge"><?= $e($rating); ?></span>
                        <span><?= $e($durationText($runtime)); ?> (<?= $runtime; ?> mins)</span>
                    </p>

                    <div style="margin-top: auto; padding-top: .75rem;">
                        <a href="<?= BASE_URL; ?>/schedules" class="btn btn-sm btn-ghost" title="Create screening for this title">
                            Schedule Screening &rarr;
                        </a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: Add New Movie Title -->
<dialog class="sched-dialog" id="addMovieModal" aria-labelledby="addMovieModalTitle">
    <form method="POST" action="<?= BASE_URL; ?>/movies/create" enctype="multipart/form-data" id="addMovieForm">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken); ?>">

        <h2 id="addMovieModalTitle">Add New Movie Title</h2>
        <p class="sched-note">Register a movie title to enable scheduling across cinema halls (FR1).</p>

        <div class="sched-field">
            <label for="movieTitleInput">Movie Title *</label>
            <input type="text" 
                   id="movieTitleInput" 
                   name="title" 
                   required 
                   placeholder="e.g. Dune: Part Two">
        </div>

        <div class="sched-row">
            <div class="sched-field">
                <label for="durationInput">Runtime (Minutes) *</label>
                <input type="number" 
                       id="durationInput" 
                       name="duration_minutes" 
                       min="1" 
                       max="600" 
                       required 
                       placeholder="e.g. 166">
            </div>

            <div class="sched-field">
                <label for="ratingSelect">MTRCB Age Rating *</label>
                <select id="ratingSelect" name="rating" required>
                    <option value="G">G - General Audiences</option>
                    <option value="PG" selected>PG - Parental Guidance</option>
                    <option value="PG-13">PG-13 - Strictly 13 &amp; Above</option>
                    <option value="R-13">R-13 - Restricted 13</option>
                    <option value="R-16">R-16 - Restricted 16</option>
                    <option value="R-18">R-18 - Adults Only 18</option>
                </select>
            </div>
        </div>

        <div class="sched-field">
            <label for="posterInput">Movie Poster Image (Optional)</label>
            <input type="file" 
                   id="posterInput" 
                   name="poster" 
                   accept="image/jpeg,image/png,image/webp">
            <small style="color: var(--c-muted); font-size: .8rem; margin-top: .2rem;">
                Supported formats: JPG, PNG, WebP (Max 5MB).
            </small>
        </div>

        <div class="sched-actions">
            <button type="button" class="btn btn-ghost" id="closeAddMovieModalBtn">Cancel</button>
            <button type="submit" class="btn">Register Movie</button>
        </div>
    </form>
</dialog>

<script>
(function() {
    'use strict';

    var modal    = document.getElementById('addMovieModal');
    var openBtn  = document.getElementById('openAddMovieBtn');
    var closeBtn = document.getElementById('closeAddMovieModalBtn');
    var titleIn  = document.getElementById('movieTitleInput');

    if (openBtn && modal) {
        openBtn.addEventListener('click', function() {
            if (typeof modal.showModal === 'function') {
                modal.showModal();
                if (titleIn) titleIn.focus();
            }
        });
    }

    if (closeBtn && modal) {
        closeBtn.addEventListener('click', function() {
            modal.close();
        });
    }
})();
</script>
