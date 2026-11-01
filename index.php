<?php
/**
 * ScreenBites - Cinema Box Office Catalog & Home
 * Modeled after PedalWorks-Dynamics procedural architecture
 * UI/UX styled from ScreenBites_oop_backup
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Now Showing Cinema & Lounge';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reviews.php';

// Quiz 3: Homepage Search Parameterized Query
$searchQuery = trim($_GET['q'] ?? '');

// 1. Fetch active movies (parameterized LIKE query if search submitted)
if (!empty($searchQuery)) {
    $mSql = "SELECT * FROM movies 
             WHERE is_active = 1 
               AND (title LIKE ? OR genre LIKE ? OR synopsis LIKE ?)
             ORDER BY movie_id ASC";
    $mStmt = mysqli_prepare($conn, $mSql);
    $like = "%" . $searchQuery . "%";
    mysqli_stmt_bind_param($mStmt, "sss", $like, $like, $like);
    mysqli_stmt_execute($mStmt);
    $resMovies = mysqli_stmt_get_result($mStmt);
} else {
    $sqlMovies = "SELECT * FROM movies WHERE is_active = 1 ORDER BY movie_id ASC";
    $resMovies = mysqli_query($conn, $sqlMovies);
}

// 2. Fetch concessions (parameterized LIKE query if search submitted)
if (!empty($searchQuery)) {
    $sSql = "SELECT * FROM products 
             WHERE is_active = 1 
               AND (product_name LIKE ? OR category LIKE ?)
             ORDER BY category ASC, product_name ASC";
    $sStmt = mysqli_prepare($conn, $sSql);
    $like = "%" . $searchQuery . "%";
    mysqli_stmt_bind_param($sStmt, "ss", $like, $like);
    mysqli_stmt_execute($sStmt);
    $resSnacks = mysqli_stmt_get_result($sStmt);

    $cbSql = "SELECT * FROM combos 
              WHERE is_active = 1 
                AND (combo_name LIKE ? OR description LIKE ?)
              ORDER BY combo_id ASC";
    $cbStmt = mysqli_prepare($conn, $cbSql);
    mysqli_stmt_bind_param($cbStmt, "ss", $like, $like);
    mysqli_stmt_execute($cbStmt);
    $resCombos = mysqli_stmt_get_result($cbStmt);
} else {
    $sqlSnacks = "SELECT * FROM products WHERE is_active = 1 ORDER BY category ASC, product_name ASC LIMIT 6";
    $resSnacks = mysqli_query($conn, $sqlSnacks);

    $sqlCombos = "SELECT * FROM combos WHERE is_active = 1 ORDER BY combo_id ASC LIMIT 2";
    $resCombos = mysqli_query($conn, $sqlCombos);
}
?>

<div class="sb-page-header">
    <div>
        <h1>Now Showing in Theaters</h1>
        <p>Book cinema tickets, reserve your VIP seats, and preview official trailers.</p>
    </div>
    <?php if (is_admin()): ?>
        <div>
            <a href="<?= BASE_URL; ?>/movies/create.php" class="btn btn-primary">+ Add New Movie</a>
            <a href="<?= BASE_URL; ?>/schedules/create.php" class="btn btn-secondary">+ Schedule Screening</a>
        </div>
    <?php endif; ?>
</div>

<!-- Homepage Search Input Bar -->
<div class="card" style="padding: 1.25rem; margin-bottom: 2rem; background: var(--c-surface, #ffffff);">
    <form method="GET" action="<?= BASE_URL; ?>/index.php" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 260px; position: relative;">
            <input type="text" 
                   name="q" 
                   value="<?= sanitize($searchQuery); ?>" 
                   placeholder="Search movies by title, genre, synopsis, or find snack concessions..." 
                   class="form-control" 
                   style="padding-left: 2.25rem;">
            <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--c-muted); font-size: 1rem;">🔍</span>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1.25rem; font-weight: 700;">
            Search Catalog
        </button>
        <?php if (!empty($searchQuery)): ?>
            <a href="<?= BASE_URL; ?>/index.php" class="btn btn-secondary" style="padding: 0.55rem 1rem;">
                Clear Filter
            </a>
        <?php endif; ?>
    </form>
    <?php if (!empty($searchQuery)): ?>
        <p style="margin: 0.75rem 0 0; font-size: 0.88rem; color: var(--c-muted);">
            Showing filtered results for: <strong>&ldquo;<?= sanitize($searchQuery); ?>&rdquo;</strong>
        </p>
    <?php endif; ?>
</div>

<!-- Movies Section -->
<div class="sb-grid" style="margin-bottom: 3rem;">
    <?php if ($resMovies && mysqli_num_rows($resMovies) > 0): ?>
        <?php while ($movie = mysqli_fetch_assoc($resMovies)): 
            $mId        = (int)$movie['movie_id'];
            $title      = (string)$movie['title'];
            $posterPath = !empty($movie['poster_path']) ? BASE_URL . '/' . ltrim($movie['poster_path'], '/') : BASE_URL . '/assets/img/default-poster.jpg';
            $rating     = (string)($movie['rating'] ?? 'PG');
            $runtime    = (int)$movie['duration_minutes'];
            $hours      = intdiv($runtime, 60);
            $mins       = $runtime % 60;
            $timeText   = ($hours > 0 ? "{$hours}h " : "") . "{$mins}m";
            $genreText  = !empty($movie['genre']) ? (string)$movie['genre'] : 'Feature Film';
            $synopsis   = !empty($movie['synopsis']) ? (string)$movie['synopsis'] : 'Experience ' . $title . ' on the big screen with high-fidelity digital projection.';
            $trailerId  = get_youtube_id($movie['trailer_url']);

            // Fetch upcoming screenings for this movie (filter out past/concluded showtimes)
            $currentDateTime = date('Y-m-d H:i:s');
            $scSql = "SELECT s.screening_id, s.screening_date, s.start_time, s.base_price, h.hall_name 
                      FROM screenings s
                      INNER JOIN halls h ON s.hall_id = h.hall_id
                      WHERE s.movie_id = {$mId}
                        AND CONCAT(s.screening_date, ' ', s.start_time) >= '{$currentDateTime}'
                      ORDER BY s.screening_date ASC, s.start_time ASC";
            $scRes = mysqli_query($conn, $scSql);
        ?>
            <div class="sb-item-card" style="display: flex; flex-direction: column;">
                <div style="position: relative;">
                    <img src="<?= $posterPath; ?>" alt="<?= sanitize($title); ?>" class="sb-item-thumb">
                    
                    <button type="button" 
                            class="sm-play-link"
                            data-open-trailer 
                            data-title="<?= sanitize($title); ?>"
                            data-rating="<?= sanitize($rating); ?>"
                            data-runtime="<?= $timeText; ?>"
                            data-genre="<?= sanitize($genreText); ?>"
                            data-synopsis="<?= sanitize($synopsis); ?>"
                            data-trailer="<?= sanitize($trailerId ?? ''); ?>"
                            style="position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.75); border: 1px solid rgba(255,255,255,0.2); border-radius: 20px; padding: 0.35rem 0.75rem; font-size: 0.8rem; color: #fff; cursor: pointer; display: flex; align-items: center; gap: 0.35rem; backdrop-filter: blur(4px);">
                        <span style="color: #ff3366;">&#9658;</span> Trailer
                    </button>
                </div>

                <div class="sb-item-body" style="flex: 1; display: flex; flex-direction: column;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
                        <h3 class="sb-item-title" style="margin-bottom: 0.25rem;"><?= sanitize($title); ?></h3>
                        <span style="border: 1px solid var(--card-border, rgba(255,255,255,0.15)); padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 600; font-size: 0.75rem;">
                            <?= sanitize($rating); ?>
                        </span>
                    </div>

                    <div class="sb-item-meta" style="margin-bottom: 0.75rem;">
                        <span><?= $timeText; ?></span> &bull; <span><?= sanitize($genreText); ?></span>
                    </div>

                    <?php $revSum = get_item_review_summary($conn, 'movie', $mId); ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; font-size: 0.82rem;">
                        <span style="color: #f59e0b; font-weight: 700;">
                            ★ <?= $revSum['count'] > 0 ? number_format($revSum['average'], 1) : 'New'; ?>
                            <span style="color: var(--c-muted); font-weight: 400; font-size: 0.75rem;">(<?= $revSum['count']; ?>)</span>
                        </span>
                        <a href="<?= BASE_URL; ?>/movie_details.php?id=<?= $mId; ?>#reviews" style="color: var(--c-indigo); font-weight: 600; text-decoration: none;">
                            Reviews &amp; Details &rarr;
                        </a>
                    </div>

                    <div style="margin-top: auto; margin-bottom: 0.75rem;">
                        <strong style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Available Screenings:
                        </strong>

                        <?php if ($scRes && mysqli_num_rows($scRes) > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                <?php while ($sc = mysqli_fetch_assoc($scRes)): 
                                    $timeLabel = date('M d, g:i A', strtotime($sc['screening_date'] . ' ' . $sc['start_time']));
                                ?>
                                    <div class="sb-dark-panel" style="display: flex; justify-content: space-between; align-items: center; padding: 0.45rem 0.65rem; border-radius: 6px; font-size: 0.82rem;">
                                        <div>
                                            <strong style="color: #ffffff;"><?= $timeLabel; ?></strong><br>
                                            <small style="color: #94a3b8;"><?= sanitize($sc['hall_name']); ?></small>
                                        </div>
                                        <div style="text-align: right;">
                                            <span style="color: var(--accent-gold, #f59e0b); font-weight: 700;"><?= format_currency($sc['base_price']); ?></span><br>
                                            <a href="<?= BASE_URL; ?>/ticketing/seat_matrix.php?screening_id=<?= (int)$sc['screening_id']; ?>" 
                                               class="btn btn-sm btn-primary" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; margin-top: 0.2rem;">
                                                Select Seats &rarr;
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div style="background: rgba(43, 45, 122, 0.04); border: 1px dashed var(--c-line, #dcdff0); border-radius: 8px; padding: 0.75rem 0.5rem; text-align: center;">
                                <span style="font-size: 0.8rem; color: var(--c-muted, #5d6180); font-weight: 600; display: block;">
                                    🎬 No upcoming showtimes scheduled
                                </span>
                                <small style="font-size: 0.72rem; color: var(--c-muted, #8b90b2); display: block; margin-top: 0.2rem;">
                                    Check back soon for new showtimes
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (is_admin()): ?>
                        <div style="margin-top: auto; padding-top: 0.65rem; border-top: 1px solid var(--card-border, rgba(255,255,255,0.08)); display: flex; gap: 0.5rem;">
                            <a href="<?= BASE_URL; ?>/movies/edit.php?id=<?= $mId; ?>" class="btn btn-sm btn-secondary" style="flex: 1; text-align: center;">Edit</a>
                            <a href="<?= BASE_URL; ?>/movies/delete.php?id=<?= $mId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this movie?');">Delete</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="grid-column: 1 / -1; padding: 3rem 1rem; text-align: center; background: #f8fafc; border: 1px dashed var(--c-line); border-radius: 8px;">
            <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">🎬</span>
            <p style="color: var(--c-muted); margin: 0; font-size: 0.95rem;">
                <?= !empty($searchQuery) ? "No movie titles matched &ldquo;" . sanitize($searchQuery) . "&rdquo;. Try searching another title or genre." : "No movies currently in catalog."; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Concessions Section -->
<div class="sb-page-header">
    <div>
        <h2>Snacks &amp; Concessions</h2>
        <p>Pair your movie with freshly popped popcorn and cold beverages.</p>
    </div>
    <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">View All Snacks &rarr;</a>
</div>

<div class="sb-grid" style="margin-bottom: 2rem;">
    <?php if ($resCombos && mysqli_num_rows($resCombos) > 0): ?>
        <?php while ($c = mysqli_fetch_assoc($resCombos)): 
            $cId = (int)$c['combo_id'];
            $ciRes = mysqli_query($conn, "SELECT ci.quantity, p.product_name, p.current_stock FROM combo_items ci INNER JOIN products p ON ci.product_id = p.product_id WHERE ci.combo_id = {$cId}");
            $parts = [];
            $maxPossible = 9999;
            while ($child = mysqli_fetch_assoc($ciRes)) {
                $needed = (int)$child['quantity'];
                $stock  = (int)$child['current_stock'];
                $parts[] = "{$needed}x " . $child['product_name'];
                $possible = ($needed > 0) ? intdiv($stock, $needed) : 9999;
                if ($possible < $maxPossible) $maxPossible = $possible;
            }
            $canFulfill = ($maxPossible > 0);
        ?>
            <div class="sb-item-card" style="border: 1px solid rgba(245, 158, 11, 0.35); background: linear-gradient(180deg, rgba(245, 158, 11, 0.04) 0%, rgba(17, 24, 39, 0.8) 100%);">
                <form method="POST" action="<?= BASE_URL; ?>/cart_update.php" style="display: flex; flex-direction: column; height: 100%;">
                    <input type="hidden" name="type" value="add_combo">
                    <input type="hidden" name="combo_id" value="<?= $cId; ?>">

                    <div class="sb-item-thumb" style="display: flex; align-items: center; justify-content: center; background: radial-gradient(circle, rgba(245, 158, 11, 0.15) 0%, rgba(15, 17, 26, 0.9) 70%); font-size: 3.5rem; position: relative;">
                        <span>🎁</span>
                        <span style="position: absolute; top: 10px; right: 10px; background: #f59e0b; color: #000; font-weight: 800; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase;">
                            Value Bundle
                        </span>
                    </div>

                    <div class="sb-item-body">
                        <h3 class="sb-item-title" style="color: #fbbf24;"><?= sanitize($c['combo_name']); ?></h3>
                        <p class="sb-item-meta">
                            <span style="color: <?= $canFulfill ? '#10b981' : '#ef4444'; ?>; font-weight: 600;">
                                <?= $canFulfill ? "In Stock ({$maxPossible} left)" : "Sold Out"; ?>
                            </span>
                        </p>
                        <div style="font-size: 0.8rem; color: #334155; background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 4px; padding: 0.3rem 0.55rem; margin-bottom: 0.5rem;">
                            <strong style="color: #b45309; font-size: 0.72rem; text-transform: uppercase; display: block; margin-bottom: 0.15rem;">Includes:</strong>
                            <?= implode(' + ', array_map('sanitize', $parts)); ?>
                        </div>
                        <div class="sb-item-price" style="color: #f59e0b;"><?= format_currency($c['combo_price']); ?></div>

                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: auto;">
                            <?php if ($canFulfill): ?>
                                <input type="number" name="quantity" value="1" min="1" max="<?= $maxPossible; ?>" class="form-control" style="width: 70px; text-align: center;">
                                <button type="submit" class="btn btn-primary" style="flex: 1; background: #d97706; border-color: #d97706;">+ Add</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary" style="flex: 1;" disabled>Sold Out</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <?php if ($resSnacks && mysqli_num_rows($resSnacks) > 0): ?>
        <?php 
        $catIcons = ['Popcorn' => '🍿', 'Beverage' => '🥤', 'Snacks' => '🌭', 'Candy' => '🍫'];
        while ($snack = mysqli_fetch_assoc($resSnacks)): 
            $sId = (int)$snack['product_id'];
            $hasImg = !empty($snack['image_path']) && file_exists(__DIR__ . '/' . $snack['image_path']);
            $icon = $catIcons[$snack['category']] ?? '🍿';
        ?>
            <div class="sb-item-card">
                <form method="POST" action="<?= BASE_URL; ?>/cart_update.php" style="display: flex; flex-direction: column; height: 100%;">
                    <input type="hidden" name="type" value="add_concession">
                    <input type="hidden" name="product_id" value="<?= $sId; ?>">

                    <?php if ($hasImg): ?>
                        <img src="<?= BASE_URL . '/' . $snack['image_path']; ?>" alt="<?= sanitize($snack['product_name']); ?>" class="sb-item-thumb">
                    <?php else: ?>
                        <div class="sb-item-thumb" style="display: flex; align-items: center; justify-content: center; background: #141724; font-size: 3.5rem;">
                            <span><?= $icon; ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="sb-item-body">
                        <h3 class="sb-item-title"><?= sanitize($snack['product_name']); ?></h3>
                        <p class="sb-item-meta"><?= sanitize($snack['category']); ?> &bull; Stock: <?= (int)$snack['current_stock']; ?></p>
                        
                        <div class="sb-item-price"><?= format_currency($snack['retail_price']); ?></div>

                        <?php $revSumSnack = get_item_review_summary($conn, 'concession', $sId); ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.35rem 0 0.6rem 0; font-size: 0.8rem;">
                            <span style="color: #f59e0b; font-weight: 700;">
                                ★ <?= $revSumSnack['count'] > 0 ? number_format($revSumSnack['average'], 1) : 'New'; ?>
                                <span style="color: var(--c-muted, #64748b); font-weight: 400; font-size: 0.72rem;">(<?= $revSumSnack['count']; ?>)</span>
                            </span>
                            <a href="<?= BASE_URL; ?>/concessions/details.php?id=<?= $sId; ?>#reviews" style="color: var(--c-indigo, #6366f1); font-weight: 600; text-decoration: none;">
                                Reviews &rarr;
                            </a>
                        </div>

                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: auto;">
                            <input type="number" name="quantity" value="1" min="1" max="<?= max(1, (int)$snack['current_stock']); ?>" class="form-control" style="width: 70px; text-align: center;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">+ Add</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <?php if ((!$resCombos || mysqli_num_rows($resCombos) === 0) && (!$resSnacks || mysqli_num_rows($resSnacks) === 0)): ?>
        <div style="grid-column: 1 / -1; padding: 2.5rem 1rem; text-align: center; background: #f8fafc; border: 1px dashed var(--c-line); border-radius: 8px;">
            <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">🍿</span>
            <p style="color: var(--c-muted); margin: 0; font-size: 0.95rem;">
                <?= !empty($searchQuery) ? "No snack concessions matched &ldquo;" . sanitize($searchQuery) . "&rdquo;." : "No snack items available."; ?>
            </p>
        </div>
    <?php endif; ?>
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

<!-- VANILLA JS TRAILER MODAL TRIGGER -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
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

    if (!trailerModal) return;

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

            if (typeof trailerModal.showModal === 'function') {
                trailerModal.showModal();
            }
        });
    });

    function closeTrailer() {
        trailerModal.close();
        if (videoContainer) videoContainer.innerHTML = '';
    }

    if (closeTrailerBtn) closeTrailerBtn.addEventListener('click', closeTrailer);
    if (modalBackdrop) modalBackdrop.addEventListener('click', closeTrailer);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
