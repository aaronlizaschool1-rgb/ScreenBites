<?php

use Core\Auth;

$e = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$movies = is_array($movies ?? null) ? $movies : [];
$isAdmin = Auth::hasRole(ROLE_ADMIN);

$durationText = function ($mins): string {
    $mins = (int)$mins;
    return $mins >= 60 ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm' : $mins . 'm';
};

$totalMovies = count($movies);

$movieThemes = [

    1  => ['theme' => 'theme-avatar',      'format' => 'IMAX 3D',            'genre' => 'Sci-Fi / Adventure',              'trailer_id' => 'd9MyW72ELq0', 'synopsis' => 'Jake Sully lives with his newfound family formed on the extrasolar moon Pandora. Once a familiar threat returns to finish what was previously started, Jake must work with Neytiri and the army of the Na\'vi race to protect their home.'],
    2  => ['theme' => 'theme-spiderman',   'format' => 'CINEMA 1 &bull; 2D', 'genre' => 'Animation / Action',              'trailer_id' => 'cqGjhVJWtEg', 'synopsis' => 'Miles Morales catapults across the Multiverse, where he encounters a team of Spider-People charged with protecting its very existence. When the heroes clash on how to handle a new threat, Miles must redefine what it means to be a hero.'],
    3  => ['theme' => 'theme-oppenheimer', 'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Biography / Drama',               'trailer_id' => 'uYPbbksJxIg', 'synopsis' => 'The story of American scientist J. Robert Oppenheimer and his role in the development of the atomic bomb during World War II, exploring the moral and scientific dilemmas of the Manhattan Project.'],
    4  => ['theme' => 'theme-mario',       'format' => 'CINEMA 2 &bull; 2D', 'genre' => 'Family / Comedy',                'trailer_id' => 'TnGl01FkMMo', 'synopsis' => 'A Brooklyn plumber named Mario travels through the Mushroom Kingdom with a princess named Peach and an anthropomorphic mushroom named Toad to find Mario\'s brother, Luigi, and to save the world from Bowser.'],

    5  => ['theme' => 'theme-spiderman',   'format' => 'IMAX 3D',            'genre' => 'Action / Superhero',              'trailer_id' => 'U4MFvAL02Vg', 'synopsis' => 'Peter Parker navigates a fresh start in New York City with no one remembering his identity, facing emerging street-level threats and new allies in a brand new era.'],
    6  => ['theme' => 'theme-default',     'format' => 'IMAX 3D &bull; ATMOS', 'genre' => 'Action / Sci-Fi / Adventure',     'trailer_id' => 'TcMBFSGVi1c', 'synopsis' => 'After the devastating events of Infinity War, the universe is in ruins. With the help of remaining allies, the Avengers assemble once more to reverse Thanos\' actions and restore balance.'],
    7  => ['theme' => 'theme-spiderman',   'format' => 'IMAX 3D',            'genre' => 'Action / Sci-Fi / Adventure',     'trailer_id' => 'JfVOs4VSpmA', 'synopsis' => 'With Spider-Man\'s identity revealed, Peter asks Doctor Strange for help. When a spell goes wrong, dangerous foes from other worlds appear, forcing Peter to discover what being Spider-Man truly means.'],
    8  => ['theme' => 'theme-spiderman',   'format' => 'CINEMA 1 &bull; 2D', 'genre' => 'Action / Sci-Fi / Comedy',        'trailer_id' => 'Nt9L1jCKGnE', 'synopsis' => 'Following the events of Avengers: Endgame, Spider-Man must step up to take on new threats in a world that has changed forever during a European school trip.'],
    9  => ['theme' => 'theme-mario',       'format' => 'DIGITAL 2D',         'genre' => 'Animation / Family / Comedy',     'trailer_id' => 'yRUAzGQ3nSY', 'synopsis' => 'After young Riley is uprooted from her Midwest life and moved to San Francisco, her emotions - Joy, Fear, Anger, Disgust and Sadness - conflict on how best to navigate a new city, house, and school.'],
    10 => ['theme' => 'theme-oppenheimer', 'format' => 'DOLBY ATMOS',        'genre' => 'Horror / Sci-Fi / Drama',         'trailer_id' => 'WR7cc5t7tv8', 'synopsis' => 'In a desolate world overrun by blind monsters with ultra-sensitive hearing, a family must navigate their lives in absolute silence to survive.'],
    11 => ['theme' => 'theme-avatar',      'format' => 'IMAX 2D',            'genre' => 'Epic / Adventure / Myth',         'trailer_id' => 'rdu1q0TxFG4', 'synopsis' => 'Homer\'s legendary epic brought to the big screen: Odysseus embarks on a perilous ten-year journey home from the Trojan War, facing mythical beasts, wrathful gods, and treacherous seas.'],
    12 => ['theme' => 'theme-mario',       'format' => 'DIGITAL 2D',         'genre' => 'Animation / Adventure / Comedy',  'trailer_id' => 'PJmX4vFEEQc', 'synopsis' => 'A young animal lover uses a scientific breakthrough to \'hop\' her consciousness into an adorable robotic beaver, uncovering high-stakes secrets in the animal kingdom.'],
    13 => ['theme' => 'theme-oppenheimer', 'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Horror / Psychological Thriller', 'trailer_id' => 'TaaDkbG3I7g', 'synopsis' => 'Bear, a hopeless romantic shop clerk, breaks a supernatural \'One Wish Willow\' artifact to win his crush Nikki\'s heart, only to unleash an obsessive, violent nightmare.'],
    14 => ['theme' => 'theme-mario',       'format' => 'DIGITAL 2D',         'genre' => 'Animation / Family / Adventure',  'trailer_id' => 'wmiIUN-7qhE', 'synopsis' => 'Woody, Buzz Lightyear, and their beloved toy family reunite to confront their biggest modern challenge yet: winning kids\' attention back from electronic screens and smart gadgets.'],
    15 => ['theme' => 'theme-oppenheimer', 'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Crime / Drama / Period',          'trailer_id' => 'oVzVdvGIC7U', 'synopsis' => 'Tommy Shelby and the notorious Birmingham razor gang face their definitive confrontation on the eve of World War II, battling rival syndicates and shifting political powers.'],
    16 => ['theme' => 'theme-oppenheimer', 'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Horror / Comedy / Analog Horror', 'trailer_id' => 'ZFy8ZgLd574', 'synopsis' => 'A bright orange 1990s television unicorn mascot named Buddy returns from the analog airwaves in this unsettling horror-comedy about vintage children\'s programming harboring a malevolent secret.'],
    17 => ['theme' => 'theme-oppenheimer', 'format' => 'DOLBY ATMOS',        'genre' => 'Horror / Mystery / Sci-Fi',       'trailer_id' => 'H4dGpz6cnHo', 'synopsis' => 'A young videographer accidentally noclipped out of reality into an endless labyrinth of yellow-wallpapered, fluorescent-lit empty rooms where terrifying entities lurk.'],
    18 => ['theme' => 'theme-default',     'format' => 'IMAX 2D',            'genre' => 'Action / Sci-Fi / Thriller',      'trailer_id' => 'iO2Br5WWCII', 'synopsis' => 'An elite field operative and special forces agent returns from retirement to confront an international syndicate in this high-octane spectacle.'],
    19 => ['theme' => 'theme-avatar',      'format' => 'CINEMA 2 &bull; 2D', 'genre' => 'Sci-Fi / Survival / Action',      'trailer_id' => 'kRfXdS2q08k', 'synopsis' => 'In an ice-age subterranean biosphere, survivors of a global cataclysm must venture into the frozen wasteland when an enigmatic transmission signals a thriving sanctuary.'],
    20 => ['theme' => 'theme-default',     'format' => 'IMAX 2D',            'genre' => 'Action / Crime / High-Octane',    'trailer_id' => '2TAOizOnNPo', 'synopsis' => 'Dominic Toretto and Brian O\'Conner reunite behind the wheel to infiltrate a ruthless heroin cartel across the Los Angeles streets and Mexican desert.'],
    21 => ['theme' => 'theme-spiderman',   'format' => 'CINEMA 1 &bull; 2D', 'genre' => 'Anime / Supernatural / Action',   'trailer_id' => 'eGJWEVnvqO4', 'synopsis' => 'Yuta Okkotsu is haunted by the cursed spirit of his childhood friend Rika. Mentored by Satoru Gojo at Tokyo Jujutsu High, he learns to wield his curse to protect humanity.'],
    22 => ['theme' => 'theme-oppenheimer', 'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Horror / Survival / Dark Comedy', 'trailer_id' => 'R4wiXj9NmEE', 'synopsis' => 'Sam Raimi directs this razor-sharp survival black comedy where corporate coworkers stranded on a remote tropical island after a plane crash engage in a twisted battle of wits and survival.'],
    23 => ['theme' => 'theme-avatar',      'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Romance / Comedy / Indie Drama',  'trailer_id' => 'PsD0NpFSADM', 'synopsis' => 'An offbeat romantic comedy about a woman who doesn\'t believe in true love, and the hopeless romantic greeting card writer who falls head over heels for her over 500 days.'],
    24 => ['theme' => 'theme-mario',       'format' => 'DIGITAL 2D',         'genre' => 'Animation / Music / Fantasy',     'trailer_id' => 'Rvr68u6k5sI', 'synopsis' => 'Aspiring young musician Miguel confronts his family\'s ancestral ban on music and enters the vibrant, colorful Land of the Dead to uncover his great-great-grandfather\'s legacy.'],
    25 => ['theme' => 'theme-spiderman',   'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Romance / Sci-Fi / Drama',        'trailer_id' => '07-QBnEkgXU', 'synopsis' => 'When their relationship turns sour, a heartbroken couple undergoes a radical medical procedure to have each other permanently erased from their memories.'],
    26 => ['theme' => 'theme-oppenheimer', 'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Romance / War / Drama',           'trailer_id' => '-J1VUwagte4', 'synopsis' => 'Thirteen-year-old fledgling writer Briony Tallis irrevocably changes the course of several lives when she accuses her older sister\'s lover of a crime he did not commit.'],
    27 => ['theme' => 'theme-oppenheimer', 'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Crime / Drama / Fantasy',         'trailer_id' => 'Ki4haFrqSrw', 'synopsis' => 'A death row corrections officer during the Great Depression discovers that an imposing, gentle inmate convicted of a horrific crime possesses a miraculous healing power.'],
    28 => ['theme' => 'theme-default',     'format' => 'CINEMA 1 &bull; 2D', 'genre' => 'Drama / Romance / Comedy',        'trailer_id' => 'bLvqoHBptjg', 'synopsis' => 'The presidencies of Kennedy and Johnson, the Vietnam War, and other historical events unfold through the perspective of an Alabama man with an IQ of 75.'],
    29 => ['theme' => 'theme-avatar',      'format' => 'DIRECTOR\'S CLUB',   'genre' => 'Drama / Thriller',                'trailer_id' => 'E_Ci-pAL4eE', 'synopsis' => 'Held captive for years in an enclosed shed, a young woman and her 5-year-old son gain their freedom, allowing the boy to experience the outside world for the first time.'],
    30 => ['theme' => 'theme-avatar',      'format' => 'IMAX 3D &bull; ATMOS', 'genre' => 'Romance / Drama / Historical',    'trailer_id' => '2e-eXJ6HgkQ', 'synopsis' => 'A seventeen-year-old aristocrat falls in love with a kind but poor artist aboard the luxurious, ill-fated R.M.S. Titanic on its maiden voyage across the Atlantic in 1912.']
];
?>

<div class="sm-cinema-page">

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
                <button type="button" class="sm-filter-pill" data-rating="R-16">R-16</button>
                <button type="button" class="sm-filter-pill" data-rating="R-18">R-18</button>
            </div>
        </div>
    </div>

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

                    <div class="sm-poster-wrap">

                        <span class="sm-format-ribbon"><?= $formatTag; ?></span>

                        <span class="sm-rating-badge rating-<?= strtolower(str_replace('-', '', $rating)); ?>">
                            <?= $e($rating); ?>
                        </span>

                        <button type="button" class="sm-bookmark-btn" title="Add to watchlist" aria-label="Bookmark">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </button>

                        <?php if ($poster): ?>
                            <img src="<?= $e($poster); ?>"
                                 alt="<?= $e($title); ?> poster"
                                 class="sm-poster-img"
                                 loading="lazy"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>

                        <div class="sm-poster-art <?= $themeClass; ?>" style="<?= $poster ? 'display: none;' : ''; ?>">
                            <div class="art-glow"></div>
                            <div class="art-film-tag"><?= $e($genreText); ?></div>
                            <div class="art-monogram"><?= $e(mb_strtoupper(mb_substr($title, 0, 1))); ?></div>
                            <div class="art-watermark"><?= $e($title); ?></div>
                        </div>

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

<dialog class="sm-trailer-modal" id="trailerModal" aria-labelledby="trailerModalTitle">
    <div class="sm-modal-backdrop" id="closeTrailerBackdrop"></div>
    <div class="sm-modal-container">
        <button type="button" class="sm-modal-close" id="closeTrailerBtn" aria-label="Close dialog">&times;</button>

        <div class="sm-modal-media">
            <div class="sm-video-placeholder" id="videoContainer">

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

<?php if ($isAdmin): ?>
<dialog class="sched-dialog" id="addMovieModal" aria-labelledby="addMovieModalTitle">
    <form method="POST" action="<?= BASE_URL; ?>/movies/create" enctype="multipart/form-data" id="addMovieForm">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken); ?>">

        <h2 id="addMovieModalTitle">Register New Movie Title</h2>
        <p class="sched-note">Add a movie to the Now Showing catalog to schedule showtimes in cinema halls.</p>

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
                    videoContainer.innerHTML = '<iframe class="sm-trailer-iframe" src="https://www.youtube-nocookie.com/embed/' + trailer + '?autoplay=1&rel=0&modestbranding=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
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

    if (trailerModal) {
        trailerModal.addEventListener('cancel', closeTrailer);
        trailerModal.addEventListener('click', function(e) {
            var rect = trailerModal.getBoundingClientRect();
            var isInDialog = (rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
                              rect.left <= e.clientX && e.clientX <= rect.left + rect.width);
            if (!isInDialog) {
                closeTrailer();
            }
        });
    }

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
