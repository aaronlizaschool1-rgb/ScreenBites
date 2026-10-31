<?php
/**
 * ScreenBites - Movie Showcase & Catalog Management
 * Modeled after PedalWorks-Dynamics procedural architecture
 * UI/UX styled from ScreenBites_oop_backup
 */

require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Now Showing Cinema Movies';
require_once dirname(__DIR__) . '/includes/header.php';

$isAdmin = is_admin();

// Fetch all active movies from MySQL
$sql = "SELECT movie_id, title, duration_minutes, rating, poster_path, trailer_url, genre, synopsis, created_at 
        FROM movies 
        WHERE is_active = 1 
        ORDER BY movie_id ASC";
$res = mysqli_query($conn, $sql);

$movies = [];
if ($res) {
    while ($m = mysqli_fetch_assoc($res)) {
        $movies[] = $m;
    }
}
$totalMovies = count($movies);
?>

<div class="sm-cinema-page">

    <!-- PAGE HEADER -->
    <div class="sm-page-header">
        <div>
            <h1 class="sm-main-title">Movies &amp; Box Office Catalog</h1>
            <p class="sm-main-subtitle">Experience blockbuster cinema in IMAX, Director's Club, and Dolby Atmos audio.</p>
        </div>

        <div class="sm-head-actions">
            <?php if ($isAdmin): ?>
                <a href="<?= BASE_URL; ?>/movies/create.php" class="btn btn-primary sm-btn-add">
                    + Add New Movie
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABS & FILTER BAR -->
    <div class="sm-tabs-bar">
        <div class="sm-tab-group" role="tablist">
            <button type="button" class="sm-tab-item active" role="tab" aria-selected="true">
                Now showing
                <span class="sm-tab-count"><?= $totalMovies; ?></span>
            </button>
        </div>

        <div class="sm-filter-bar">
            <div class="sm-search-box">
                <input type="search" id="movieSearch" placeholder="Search movies, genre..." aria-label="Search movies">
            </div>

            <div class="sm-rating-filters" role="group" aria-label="Rating filter">
                <button type="button" class="sm-filter-pill active" data-rating="all">All</button>
                <button type="button" class="sm-filter-pill" data-rating="G">G</button>
                <button type="button" class="sm-filter-pill" data-rating="PG">PG</button>
                <button type="button" class="sm-filter-pill" data-rating="PG-13">PG-13</button>
                <button type="button" class="sm-filter-pill" data-rating="R-13">R-13</button>
                <button type="button" class="sm-filter-pill" data-rating="R-16">R-16</button>
                <button type="button" class="sm-filter-pill" data-rating="R-18">R-18</button>
            </div>
        </div>
    </div>

    <!-- MOVIE CARDS GRID -->
    <div class="sm-cinema-grid" id="smMoviesGrid">
        <?php if (empty($movies)): ?>
            <div class="card sm-empty-card" style="grid-column: 1 / -1; padding: 3rem; text-align: center;">
                <p class="empty-note">No movies currently in the Now Showing catalog. Click "+ Add New Movie" to register cinema titles.</p>
            </div>
        <?php else: ?>
            <?php foreach ($movies as $movie):
                $mId       = (int)$movie['movie_id'];
                $title     = (string)$movie['title'];
                $poster    = !empty($movie['poster_path']) ? BASE_URL . '/' . ltrim((string)$movie['poster_path'], '/') : null;
                $rating    = (string)($movie['rating'] ?? 'PG');
                $runtime   = (int)$movie['duration_minutes'];
                $hours     = intdiv($runtime, 60);
                $mins      = $runtime % 60;
                $timeText  = ($hours > 0 ? "{$hours}h " : "") . "{$mins}m";
                $genreText = !empty($movie['genre']) ? (string)$movie['genre'] : 'Feature Film';
                $synopsis  = !empty($movie['synopsis']) ? (string)$movie['synopsis'] : 'Experience ' . $title . ' on the big screen with high-fidelity digital projection and immersive sound.';
                $trailerId = get_youtube_id($movie['trailer_url']);
            ?>
                <article class="sm-movie-card"
                         data-title="<?= sanitize(strtolower($title . ' ' . $genreText)); ?>"
                         data-rating="<?= sanitize($rating); ?>">

                    <div class="sm-poster-wrap">
                        <span class="sm-format-ribbon">CINEMA</span>

                        <span class="sm-rating-badge rating-<?= strtolower(str_replace('-', '', $rating)); ?>">
                            <?= sanitize($rating); ?>
                        </span>

                        <?php if ($poster): ?>
                            <img src="<?= $poster; ?>"
                                 alt="<?= sanitize($title); ?> poster"
                                 class="sm-poster-img"
                                 loading="lazy"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>

                        <div class="sm-poster-art theme-default" style="<?= $poster ? 'display: none;' : ''; ?>">
                            <div class="art-glow"></div>
                            <div class="art-film-tag"><?= sanitize($genreText); ?></div>
                            <div class="art-monogram"><?= sanitize(mb_strtoupper(mb_substr($title, 0, 1))); ?></div>
                            <div class="art-watermark"><?= sanitize($title); ?></div>
                        </div>

                        <!-- Poster Hover Overlay -->
                        <div class="sm-poster-overlay">
                            <div class="overlay-meta">
                                <span>&#9201; <?= $timeText; ?></span>
                                <span>&#127916; <?= sanitize($genreText); ?></span>
                            </div>

                            <div class="overlay-buttons">
                                <button type="button"
                                        class="sm-btn-trailer"
                                        data-open-trailer
                                        data-title="<?= sanitize($title); ?>"
                                        data-rating="<?= sanitize($rating); ?>"
                                        data-runtime="<?= $timeText; ?>"
                                        data-genre="<?= sanitize($genreText); ?>"
                                        data-synopsis="<?= sanitize($synopsis); ?>"
                                        data-trailer="<?= sanitize($trailerId ?? ''); ?>">
                                    <span class="play-icon">&#9658;</span> Play trailer
                                </button>

                                <a href="<?= BASE_URL; ?>/schedules/index.php" class="btn sm-btn-book">
                                    Showtimes &rarr;
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="sm-card-details">
                        <div class="sm-card-title-row">
                            <h2 class="sm-movie-title" title="<?= sanitize($title); ?>">
                                <?= sanitize($title); ?>
                            </h2>
                            <span class="sm-inline-rating sm-rating-<?= strtolower(str_replace('-', '', $rating)); ?>">
                                <?= sanitize($rating); ?>
                            </span>
                        </div>

                        <div class="sm-card-meta-row">
                            <span class="sm-runtime"><?= $timeText; ?></span>
                            <span class="sm-dot">&bull;</span>
                            <span class="sm-genre"><?= sanitize($genreText); ?></span>
                        </div>

                        <div class="sm-card-actions">
                            <button type="button"
                                    class="sm-play-link"
                                    data-open-trailer
                                    data-title="<?= sanitize($title); ?>"
                                    data-rating="<?= sanitize($rating); ?>"
                                    data-runtime="<?= $timeText; ?>"
                                    data-genre="<?= sanitize($genreText); ?>"
                                    data-synopsis="<?= sanitize($synopsis); ?>"
                                    data-trailer="<?= sanitize($trailerId ?? ''); ?>">
                                <span class="play-circle">&#9658;</span> Play trailer
                            </button>

                            <a href="<?= BASE_URL; ?>/schedules/index.php" class="sm-link-schedules">
                                Showtimes &rarr;
                            </a>
                        </div>

                        <?php if ($isAdmin): ?>
                            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--c-line, rgba(255,255,255,0.08)); display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <a href="<?= BASE_URL; ?>/movies/edit.php?id=<?= $mId; ?>" class="btn btn-sm btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">Edit</a>
                                <a href="<?= BASE_URL; ?>/movies/delete.php?id=<?= $mId; ?>" class="btn btn-sm btn-danger" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;" onclick="return confirm('Delete this movie?');">Delete</a>
                            </div>
                        <?php endif; ?>
                    </div>

                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- NATIVE HTML5 MOVIE TRAILER DIALOG -->
<dialog class="sm-trailer-modal" id="trailerModal" aria-labelledby="trailerModalTitle">
    <div class="sm-modal-backdrop" id="closeTrailerBackdrop"></div>
    <div class="sm-modal-container">
        <button type="button" class="sm-modal-close" id="closeTrailerBtn" aria-label="Close dialog">&times;</button>

        <div class="sm-modal-media">
            <div class="sm-video-placeholder" id="videoContainer"></div>
        </div>

        <div class="sm-modal-details">
            <div class="sm-modal-header">
                <div>
                    <div class="sm-modal-tags">
                        <span class="rating-badge" id="modalRatingBadge">PG-13</span>
                        <span class="sm-modal-runtime" id="modalRuntimeText">2h 10m</span>
                        <span class="sm-format-pill" id="modalGenreBadge">Cinema Feature</span>
                    </div>
                    <h2 class="sm-modal-title" id="trailerModalTitle">Movie Title</h2>
                </div>
                <a href="<?= BASE_URL; ?>/schedules/index.php" class="btn sm-modal-book-btn">
                    Book Tickets Now
                </a>
            </div>

            <div class="sm-modal-synopsis">
                <h3>Synopsis</h3>
                <p id="modalSynopsisText">Movie synopsis description.</p>
            </div>
        </div>
    </div>
</dialog>

<!-- VANILLA CONTROLLER FOR FILTERING & TRAILER MODAL -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // 1. Movie Search & Rating Filters
    var searchInput  = document.getElementById('movieSearch');
    var ratingPills  = document.querySelectorAll('.sm-filter-pill');
    var movieCards   = document.querySelectorAll('.sm-movie-card');
    var activeRating = 'all';

    function filterMovies() {
        var query = (searchInput ? searchInput.value : '').trim().toLowerCase();

        movieCards.forEach(function(card) {
            var title = card.getAttribute('data-title') || '';
            var rating = card.getAttribute('data-rating') || '';

            var matchSearch = !query || title.indexOf(query) !== -1;
            var matchRating = (activeRating === 'all' || rating === activeRating);

            card.style.display = (matchSearch && matchRating) ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterMovies);
    }

    ratingPills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            ratingPills.forEach(function(p) { p.classList.remove('active'); });
            pill.classList.add('active');
            activeRating = pill.getAttribute('data-rating');
            filterMovies();
        });
    });

    // 2. Trailer Modal Interaction
    var trailerModal      = document.getElementById('trailerModal');
    var closeTrailerBtn   = document.getElementById('closeTrailerBtn');
    var modalBackdrop     = document.getElementById('closeTrailerBackdrop');
    var modalTitle        = document.getElementById('trailerModalTitle');
    var modalRating       = document.getElementById('modalRatingBadge');
    var modalRuntime      = document.getElementById('modalRuntimeText');
    var modalGenre        = document.getElementById('modalGenreBadge');
    var modalSynopsis     = document.getElementById('modalSynopsisText');
    var videoContainer    = document.getElementById('videoContainer');
    var trailerTriggers   = document.querySelectorAll('[data-open-trailer]');

    trailerTriggers.forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            var title    = trigger.getAttribute('data-title') || 'Featured Film';
            var rating   = trigger.getAttribute('data-rating') || 'PG';
            var runtime  = trigger.getAttribute('data-runtime') || '';
            var genre    = trigger.getAttribute('data-genre') || 'Feature Film';
            var synopsis = trigger.getAttribute('data-synopsis') || '';
            var trailer  = trigger.getAttribute('data-trailer');

            if (modalTitle) modalTitle.textContent = title;
            if (modalRating) modalRating.textContent = rating;
            if (modalRuntime) modalRuntime.textContent = runtime;
            if (modalGenre) modalGenre.textContent = genre;
            if (modalSynopsis) modalSynopsis.textContent = synopsis;

            if (videoContainer) {
                if (trailer) {
                    videoContainer.innerHTML = '<iframe class="sm-trailer-iframe" src="https://www.youtube-nocookie.com/embed/' + encodeURIComponent(trailer) + '?autoplay=1&rel=0&modestbranding=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
                } else {
                    videoContainer.innerHTML = '<div class="sm-player-screen"><div class="sm-player-content"><p class="sm-player-label">No official trailer registered for ' + title + '</p></div></div>';
                }
            }

            if (trailerModal && typeof trailerModal.showModal === 'function') {
                trailerModal.showModal();
            }
        });
    });

    function closeTrailer() {
        if (trailerModal) {
            trailerModal.close();
            if (videoContainer) videoContainer.innerHTML = '';
        }
    }

    if (closeTrailerBtn) closeTrailerBtn.addEventListener('click', closeTrailer);
    if (modalBackdrop) modalBackdrop.addEventListener('click', closeTrailer);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
