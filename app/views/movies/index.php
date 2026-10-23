<?php
/**
 * ScreenBites: Movies Catalog (Inspired by SM Cinema UI)
 * 
 * Cinematic "Now Showing" showcase with portrait posters, MTRCB rating pills,
 * interactive trailers, format badges, and hall scheduling triggers (FR1).
 * 
 * @var array<int, array<string, mixed>> $movies
 * @var string|null                      $flashSuccess
 * @var string|null                      $flashError
 * @var string                           $csrfToken
 */

use Core\Auth;

$e = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$movies = is_array($movies ?? null) ? $movies : [];
$isAdmin = Auth::hasRole(ROLE_ADMIN);

$durationText = function ($mins): string {
    $mins = (int)$mins;
    return $mins >= 60 ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm' : $mins . 'm';
};

$totalMovies = count($movies);

// Format and theme lookup for initial movie showcase
$movieThemes = [
    1 => ['theme' => 'theme-avatar',      'format' => 'IMAX 3D',          'genre' => 'Sci-Fi / Adventure', 'trailer_id' => 'd9MyW72ELq0', 'synopsis' => 'Jake Sully lives with his newfound family formed on the extrasolar moon Pandora. Once a familiar threat returns to finish what was previously started, Jake must work with Neytiri and the army of the Na\'vi race to protect their home.'],
    2 => ['theme' => 'theme-spiderman',   'format' => 'CINEMA 1 &bull; 2D', 'genre' => 'Animation / Action', 'trailer_id' => 'cqGjhVJWtEg', 'synopsis' => 'Miles Morales catapults across the Multiverse, where he encounters a team of Spider-People charged with protecting its very existence. When the heroes clash on how to handle a new threat, Miles must redefine what it means to be a hero.'],
    3 => ['theme' => 'theme-oppenheimer', 'format' => 'DIRECTOR\'S CLUB', 'genre' => 'Biography / Drama',   'trailer_id' => 'uYPbbksJxIg', 'synopsis' => 'The story of American scientist J. Robert Oppenheimer and his role in the development of the atomic bomb during World War II, exploring the moral and scientific dilemmas of the Manhattan Project.'],
    4 => ['theme' => 'theme-mario',       'format' => 'CINEMA 2 &bull; 2D', 'genre' => 'Family / Comedy',    'trailer_id' => 'TnGl01FkMMo', 'synopsis' => 'A Brooklyn plumber named Mario travels through the Mushroom Kingdom with a princess named Peach and an anthropomorphic mushroom named Toad to find Mario\'s brother, Luigi, and to save the world from Bowser.']
];
?>

<div class="sm-cinema-page">

    <!-- Top Movies Bar -->
    <div class="sm-page-header">
        <div>
            <h1 class="sm-main-title">Movies</h1>
            <p class="sm-main-subtitle">Experience blockbuster cinema in IMAX, Director's Club, and Digital Surround sound.</p>
        </div>

        <div class="sm-head-actions">
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-primary sm-btn-add" id="openAddMovieBtn">
                    + Add New Movie
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- SM Cinema Tabs Bar (Now Showing only - No Coming Soon) -->
    <div class="sm-tabs-bar">
        <div class="sm-tab-group" role="tablist">
            <button type="button" class="sm-tab-item active" role="tab" aria-selected="true">
                Now showing
                <span class="sm-tab-count"><?= $totalMovies; ?></span>
            </button>
        </div>

        <!-- Filter & Search Controls -->
        <div class="sm-filter-bar">
            <div class="sm-search-box">
                <input type="search" id="movieSearch" placeholder="Search movies, genre..." aria-label="Search movies">
            </div>

            <div class="sm-rating-filters" role="group" aria-label="Rating filter">
                <button type="button" class="sm-filter-pill active" data-rating="all">All</button>
                <button type="button" class="sm-filter-pill" data-rating="G">G</button>
                <button type="button" class="sm-filter-pill" data-rating="PG">PG</button>
                <button type="button" class="sm-filter-pill" data-rating="PG-13">PG-13</button>
                <button type="button" class="sm-filter-pill" data-rating="R-16">R-16</button>
                <button type="button" class="sm-filter-pill" data-rating="R-18">R-18</button>
            </div>
        </div>
    </div>

    <!-- Movie Posters Grid (SM Cinema Inspired) -->
    <div class="sm-cinema-grid" id="smMoviesGrid">
        <?php if (empty($movies)): ?>
            <div class="card sm-empty-card" style="grid-column: 1 / -1;">
                <p class="empty-note">No movies currently in the Now Showing catalog. Click "+ Add New Movie" to register cinema titles.</p>
            </div>
        <?php else: ?>
            <?php foreach ($movies as $movie): 
                $mId      = (int)$movie['movie_id'];
                $title    = (string)$movie['title'];
                $poster   = !empty($movie['poster_path']) ? BASE_URL . '/' . ltrim((string)$movie['poster_path'], '/') : null;
                $rating   = (string)($movie['rating'] ?? 'PG');
                $runtime  = (int)$movie['duration_minutes'];
                $timeText = $durationText($runtime);

                $meta     = $movieThemes[$mId] ?? [
                    'theme'      => 'theme-default',
                    'format'     => 'DIGITAL 2D',
                    'genre'      => 'Feature Film',
                    'trailer_id' => null,
                    'synopsis'   => 'Experience ' . $title . ' on the big screen with high-fidelity digital projection and immersive sound.'
                ];
                $themeClass = $meta['theme'];
                $formatTag  = $meta['format'];
                $genreText  = $meta['genre'];
                $synopsis   = $meta['synopsis'];
                $trailerId  = $meta['trailer_id'];
            ?>
                <article class="sm-movie-card" 
                         data-title="<?= $e(strtolower($title . ' ' . $genreText)); ?>" 
                         data-rating="<?= $e($rating); ?>">
                    
                    <!-- Poster Artwork Frame -->
                    <div class="sm-poster-wrap">
                        
                        <!-- Format Ribbon (Top Left) -->
                        <span class="sm-format-ribbon"><?= $formatTag; ?></span>

                        <!-- MTRCB Rating Badge (Top Right) -->
                        <span class="sm-rating-badge rating-<?= strtolower(str_replace('-', '', $rating)); ?>">
                            <?= $e($rating); ?>
                        </span>

                        <!-- Bookmark Icon -->
                        <button type="button" class="sm-bookmark-btn" title="Add to watchlist" aria-label="Bookmark">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </button>

                        <!-- Poster Image or Stylized Cinematic Backdrop Fallback -->
                        <?php if ($poster): ?>
                            <img src="<?= $e($poster); ?>" 
                                 alt="<?= $e($title); ?> poster" 
                                 class="sm-poster-img"
                                 loading="lazy"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>

                        <!-- Cool Stylized Cinematic Backdrop (renders if image fails or missing) -->
                        <div class="sm-poster-art <?= $themeClass; ?>" style="<?= $poster ? 'display: none;' : ''; ?>">
                            <div class="art-glow"></div>
                            <div class="art-film-tag"><?= $e($genreText); ?></div>
                            <div class="art-monogram"><?= $e(mb_strtoupper(mb_substr($title, 0, 1))); ?></div>
                            <div class="art-watermark"><?= $e($title); ?></div>
                        </div>

                        <!-- Hover Overlay with Cool Glassmorphic CTAs -->
                        <div class="sm-poster-overlay">
                            <div class="overlay-meta">
                                <span>&#9201; <?= $e($timeText); ?></span>
                                <span>&#127916; <?= $e($genreText); ?></span>
                            </div>

                            <div class="overlay-buttons">
                                <button type="button" 
                                        class="sm-btn-trailer" 
                                        data-open-trailer
                                        data-title="<?= $e($title); ?>"
                                        data-rating="<?= $e($rating); ?>"
                                        data-runtime="<?= $e($timeText); ?>"
                                        data-synopsis="<?= $e($synopsis); ?>"
                                        data-trailer="<?= $e($trailerId ?? ''); ?>">
                                    <span class="play-icon">&#9658;</span> Play trailer
                                </button>

                                <a href="<?= BASE_URL; ?>/schedules" class="btn sm-btn-book">
                                    Get Tickets &rarr;
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Movie Info Details Below Poster (SM Cinema Signature Style) -->
                    <div class="sm-card-details">
                        <div class="sm-card-title-row">
                            <h2 class="sm-movie-title" title="<?= $e($title); ?>">
                                <?= $e($title); ?>
                            </h2>
                            <span class="sm-inline-rating sm-rating-<?= strtolower(str_replace('-', '', $rating)); ?>">
                                <?= $e($rating); ?>
                            </span>
                        </div>

                        <div class="sm-card-meta-row">
                            <span class="sm-runtime"><?= $e($timeText); ?></span>
                            <span class="sm-dot">&bull;</span>
                            <span class="sm-genre"><?= $e($genreText); ?></span>
                        </div>

                        <div class="sm-card-actions">
                            <button type="button" 
                                    class="sm-play-link" 
                                    data-open-trailer
                                    data-title="<?= $e($title); ?>"
                                    data-rating="<?= $e($rating); ?>"
                                    data-runtime="<?= $e($timeText); ?>"
                                    data-synopsis="<?= $e($synopsis); ?>"
                                    data-trailer="<?= $e($trailerId ?? ''); ?>">
                                <span class="play-circle">&#9658;</span> Play trailer
                            </button>

                            <a href="<?= BASE_URL; ?>/schedules" class="sm-link-schedules">
                                Showtimes &rarr;
                            </a>
                        </div>
                    </div>

                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Interactive Cool Trailer & Movie Info Modal -->
<dialog class="sm-trailer-modal" id="trailerModal" aria-labelledby="trailerModalTitle">
    <div class="sm-modal-backdrop" id="closeTrailerBackdrop"></div>
    <div class="sm-modal-container">
        <button type="button" class="sm-modal-close" id="closeTrailerBtn" aria-label="Close dialog">&times;</button>
        
        <div class="sm-modal-media">
            <div class="sm-video-placeholder" id="videoContainer">
                <!-- Video player embed or simulated player -->
                <div class="sm-player-screen">
                    <span class="sm-player-glow"></span>
                    <div class="sm-player-content">
                        <div class="sm-player-play-btn" id="playTrailerVideoBtn">
                            <span>&#9658;</span>
                        </div>
                        <p class="sm-player-label">Watch Official Cinematic Trailer</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="sm-modal-details">
            <div class="sm-modal-header">
                <div>
                    <div class="sm-modal-tags">
                        <span class="rating-badge" id="modalRatingBadge">PG-13</span>
                        <span class="sm-modal-runtime" id="modalRuntimeText">2h 46m</span>
                        <span class="sm-format-pill">Dolby Atmos &bull; IMAX</span>
                    </div>
                    <h2 class="sm-modal-title" id="trailerModalTitle">Movie Title</h2>
                </div>
                <a href="<?= BASE_URL; ?>/schedules" class="btn sm-modal-book-btn">
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

<!-- Modal: Add New Movie Title (Admin FR1) -->
<?php if ($isAdmin): ?>
<dialog class="sched-dialog" id="addMovieModal" aria-labelledby="addMovieModalTitle">
    <form method="POST" action="<?= BASE_URL; ?>/movies/create" enctype="multipart/form-data" id="addMovieForm">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken); ?>">

        <h2 id="addMovieModalTitle">Register New Movie Title</h2>
        <p class="sched-note">Add a movie to the Now Showing catalog to schedule showtimes in cinema halls (FR1).</p>

        <div class="sched-field">
            <label for="movieTitleInput">Movie Title *</label>
            <input type="text" 
                   id="movieTitleInput" 
                   name="title" 
                   required 
                   placeholder="e.g. Gladiator II">
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
                       placeholder="e.g. 148">
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
            <label for="posterInput">Movie Poster Image Artwork (Optional)</label>
            <input type="file" 
                   id="posterInput" 
                   name="poster" 
                   accept="image/jpeg,image/png,image/webp">
            <small style="color: var(--c-muted); font-size: .8rem; margin-top: .2rem;">
                Supported file formats: JPG, PNG, WebP (Max 5MB). 2:3 vertical portrait recommended.
            </small>
        </div>

        <div class="sched-actions">
            <button type="button" class="btn btn-ghost" id="closeAddMovieModalBtn">Cancel</button>
            <button type="submit" class="btn">Add to Now Showing</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script>
(function() {
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
    var modalSynopsis     = document.getElementById('modalSynopsisText');
    var videoContainer    = document.getElementById('videoContainer');
    var trailerTriggers   = document.querySelectorAll('[data-open-trailer]');

    trailerTriggers.forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            var title    = trigger.getAttribute('data-title');
            var rating   = trigger.getAttribute('data-rating');
            var runtime  = trigger.getAttribute('data-runtime');
            var synopsis = trigger.getAttribute('data-synopsis');
            var trailer  = trigger.getAttribute('data-trailer');

            if (modalTitle) modalTitle.textContent = title;
            if (modalRating) modalRating.textContent = rating;
            if (modalRuntime) modalRuntime.textContent = runtime;
            if (modalSynopsis) modalSynopsis.textContent = synopsis;

            // Load YouTube embed if available
            if (videoContainer) {
                if (trailer) {
                    videoContainer.innerHTML = '<iframe class="sm-trailer-iframe" src="https://www.youtube-nocookie.com/embed/' + trailer + '?autoplay=1&rel=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                } else {
                    videoContainer.innerHTML = '<div class="sm-player-screen"><div class="sm-player-content"><p class="sm-player-label">Official Trailer for ' + title + '</p></div></div>';
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

    // 3. Add Movie Dialog
    var addModal    = document.getElementById('addMovieModal');
    var openAddBtn  = document.getElementById('openAddMovieBtn');
    var closeAddBtn = document.getElementById('closeAddMovieModalBtn');
    var titleIn     = document.getElementById('movieTitleInput');

    if (openAddBtn && addModal) {
        openAddBtn.addEventListener('click', function() {
            if (typeof addModal.showModal === 'function') {
                addModal.showModal();
                if (titleIn) titleIn.focus();
            }
        });
    }

    if (closeAddBtn && addModal) {
        closeAddBtn.addEventListener('click', function() {
            addModal.close();
        });
    }
})();
</script>
