<?php
/**
 * ScreenBites - Movie Showcase & Verified Customer Reviews
 * Fulfills MP4: Review CRUD with Regex Masking (100% Pure Procedural PHP)
 * Dynamic review showcase, purchase verification check, and author edit controls
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/reviews.php';

$movieId = (int)($_GET['id'] ?? 0);
if ($movieId <= 0) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// 1. Fetch Movie Information
$mStmt = mysqli_prepare($conn, "SELECT movie_id, title, duration_minutes, rating, poster_path, trailer_url, genre, synopsis FROM movies WHERE movie_id = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($mStmt, "i", $movieId);
mysqli_stmt_execute($mStmt);
$movie = mysqli_fetch_assoc(mysqli_stmt_get_result($mStmt));
mysqli_stmt_close($mStmt);

if (!$movie) {
    set_flash('error', 'Movie not found or is currently inactive.');
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// 2. Fetch Active Upcoming Screenings for this Movie (filtered by >= NOW())
$screenings = [];
$scSql = "SELECT s.screening_id, s.screening_date, s.start_time, s.base_price, h.hall_name 
          FROM screenings s
          INNER JOIN halls h ON s.hall_id = h.hall_id
          WHERE s.movie_id = ? 
            AND CONCAT(s.screening_date, ' ', s.start_time) >= NOW()
          ORDER BY s.screening_date ASC, s.start_time ASC";

$scStmt = mysqli_prepare($conn, $scSql);
if ($scStmt) {
    mysqli_stmt_bind_param($scStmt, "i", $movieId);
    mysqli_stmt_execute($scStmt);
    $scRes = mysqli_stmt_get_result($scStmt);
    while ($scRow = mysqli_fetch_assoc($scRes)) {
        $screenings[] = $scRow;
    }
    mysqli_stmt_close($scStmt);
}

// 3. Fetch Reviews and Summary (MP4 Constraint: Display Reviews)
$reviews = get_item_reviews($conn, 'movie', $movieId);
$reviewSummary = get_item_review_summary($conn, 'movie', $movieId);

// 4. Fetch Movie Stills Gallery (MP1 Upgrade: Multiple Photo Uploads & Gallery View)
$galleryStills = [];
$gStmt = mysqli_prepare($conn, "SELECT image_id, image_path FROM movie_images WHERE movie_id = ? AND is_primary = 0 ORDER BY image_id ASC");
if ($gStmt) {
    mysqli_stmt_bind_param($gStmt, "i", $movieId);
    mysqli_stmt_execute($gStmt);
    $gRes = mysqli_stmt_get_result($gStmt);
    while ($gRow = mysqli_fetch_assoc($gRes)) {
        $galleryStills[] = $gRow;
    }
    mysqli_stmt_close($gStmt);
}

// 5. Check if current user is logged in, verified, and if they already submitted a review
$isUserLoggedIn = is_logged_in();
$userId = $isUserLoggedIn ? (int)current_user()['user_id'] : 0;
$isVerifiedBuyer = $isUserLoggedIn ? has_user_purchased_item($conn, $userId, 'movie', $movieId) : false;
$userExistingReview = ($isUserLoggedIn && $isVerifiedBuyer) ? get_user_item_review($conn, $userId, 'movie', $movieId) : null;

$pageTitle = $movie['title'] . ' - Movie Details & Reviews';
require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 1.25rem;">
    <a href="<?= BASE_URL; ?>/index.php" style="color: var(--c-muted); text-decoration: none; font-size: 0.88rem; font-weight: 500;">
        &larr; Back to Now Showing
    </a>
</div>

<!-- Movie Details Hero Showcase -->
<div class="card" style="padding: 2rem; margin-bottom: 2rem;">
    <div style="display: grid; grid-template-columns: 240px 1fr; gap: 2rem; align-items: start;" class="movie-details-layout">
        
        <!-- Poster & Rating Summary -->
        <div>
            <?php 
                $posterSrc = (!empty($movie['poster_path']) && file_exists(dirname(__DIR__) . '/' . $movie['poster_path']))
                    ? BASE_URL . '/' . htmlspecialchars($movie['poster_path'])
                    : BASE_URL . '/assets/img/logo.png';
            ?>
            <img src="<?= $posterSrc; ?>" 
                 alt="<?= sanitize($movie['title']); ?>" 
                 style="width: 100%; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); object-fit: cover; aspect-ratio: 2/3;">
            
            <div style="text-align: center; margin-top: 1rem; background: #f8fafc; border: 1px solid var(--c-line); padding: 0.75rem; border-radius: 8px;">
                <div style="color: #f59e0b; font-size: 1.35rem; font-weight: 800;">
                    ★ <?= number_format($reviewSummary['average'], 1); ?> <span style="font-size: 0.85rem; color: var(--c-muted); font-weight: 500;">/ 5.0</span>
                </div>
                <small style="color: var(--c-muted); font-size: 0.8rem;">
                    Based on <?= number_format($reviewSummary['count']); ?> verified customer review<?= $reviewSummary['count'] === 1 ? '' : 's'; ?>
                </small>
            </div>

            <?php if (!empty($galleryStills)): ?>
                <div style="margin-top: 1.25rem;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--c-muted); text-transform: uppercase; margin-bottom: 0.45rem; letter-spacing: 0.5px;">
                        Movie Stills (<?= count($galleryStills); ?>)
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.4rem;">
                        <?php foreach ($galleryStills as $still): ?>
                            <a href="<?= BASE_URL . '/' . htmlspecialchars($still['image_path']); ?>" target="_blank" style="display: block; border-radius: 6px; overflow: hidden; border: 1px solid var(--c-line); background: #000;" title="View Fullscreen Still">
                                <img src="<?= BASE_URL . '/' . htmlspecialchars($still['image_path']); ?>" alt="Movie Still" style="width: 100%; height: 52px; object-fit: cover; display: block;">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Movie Info & Upcoming Showtimes -->
        <div>
            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                <span class="badge" style="background: var(--c-indigo); color: #fff; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.8rem;">
                    <?= sanitize($movie['rating']); ?>
                </span>
                <span style="color: var(--c-muted); font-size: 0.88rem;">
                    <?= (int)$movie['duration_minutes']; ?> mins &bull; <?= sanitize($movie['genre'] ?? 'Feature Film'); ?>
                </span>
            </div>

            <h1 style="font-size: 2rem; margin: 0 0 1rem; color: var(--c-ink); line-height: 1.2;">
                <?= sanitize($movie['title']); ?>
            </h1>

            <p style="color: #475569; font-size: 0.98rem; line-height: 1.6; margin-bottom: 1.5rem;">
                <?= nl2br(sanitize($movie['synopsis'] ?? 'No synopsis provided for this film.')); ?>
            </p>

            <!-- Upcoming Screenings Section -->
            <div style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.1rem; color: var(--c-ink); margin: 0 0 0.75rem;">Available Showtimes</h3>
                <?php if (!empty($screenings)): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php foreach ($screenings as $sc): 
                            $dateStr = date('M d', strtotime($sc['screening_date']));
                            $timeStr = date('g:i A', strtotime($sc['start_time']));
                        ?>
                            <div style="background: #f1f5f9; border: 1px solid var(--c-line); border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; gap: 1rem;">
                                <div>
                                    <strong style="color: var(--c-indigo); font-size: 0.92rem; display: block;"><?= $dateStr . ', ' . $timeStr; ?></strong>
                                    <small style="color: var(--c-muted); font-size: 0.78rem;"><?= sanitize($sc['hall_name']); ?> &bull; <?= format_currency($sc['base_price']); ?></small>
                                </div>
                                <a href="<?= BASE_URL; ?>/ticketing/seat_matrix.php?screening_id=<?= (int)$sc['screening_id']; ?>" class="btn btn-sm btn-primary">
                                    Select Seats &rarr;
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--c-muted); font-size: 0.88rem; background: #f8fafc; border: 1px dashed var(--c-line); padding: 0.75rem 1rem; border-radius: 6px;">
                        No upcoming showtimes scheduled at this time.
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($movie['trailer_url'])): ?>
                <div>
                    <h3 style="font-size: 1.1rem; color: var(--c-ink); margin: 0 0 0.75rem;">Official Trailer</h3>
                    <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 8px; max-width: 560px;">
                        <iframe src="https://www.youtube-nocookie.com/embed/<?= htmlspecialchars($movie['trailer_url']); ?>" 
                                title="YouTube Trailer" 
                                style="position: absolute; top:0; left: 0; width: 100%; height: 100%; border:0;" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                        </iframe>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($galleryStills)): ?>
                <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--c-line);">
                    <h3 style="font-size: 1.05rem; color: var(--c-ink); margin: 0 0 0.75rem;">Production Stills &amp; Gallery</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 0.65rem;">
                        <?php foreach ($galleryStills as $still): ?>
                            <a href="<?= BASE_URL . '/' . htmlspecialchars($still['image_path']); ?>" target="_blank" style="display: block; border-radius: 6px; overflow: hidden; border: 1px solid var(--c-line); box-shadow: 0 2px 4px rgba(0,0,0,0.05);" title="Open Still">
                                <img src="<?= BASE_URL . '/' . htmlspecialchars($still['image_path']); ?>" alt="Movie Still" style="width: 100%; height: 90px; object-fit: cover; display: block;">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- VERIFIED CUSTOMER REVIEWS SECTION -->
<!-- ====================================================================== -->
<div class="card" id="reviews" style="padding: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--c-line); padding-bottom: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.35rem; margin: 0; color: var(--c-ink);">Verified Customer Reviews</h2>
            <p style="margin: 0.25rem 0 0; color: var(--c-muted); font-size: 0.85rem;">
                Reviews from cinema guests who purchased tickets for this film.
            </p>
        </div>
        <div style="text-align: right;">
            <span style="font-size: 1.4rem; color: #f59e0b; font-weight: 800;">★ <?= number_format($reviewSummary['average'], 1); ?></span>
            <small style="color: var(--c-muted); display: block; font-size: 0.78rem;"><?= $reviewSummary['count']; ?> review<?= $reviewSummary['count'] === 1 ? '' : 's'; ?></small>
        </div>
    </div>

    <!-- Review Form Section (Purchase Verification Protected) -->
    <div style="background: #f8fafc; border: 1px solid var(--c-line); border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
        <?php if (!$isUserLoggedIn): ?>
            <div style="text-align: center; padding: 1rem;">
                <p style="color: var(--c-muted); margin-bottom: 0.75rem; font-size: 0.95rem;">
                    Sign in to share your experience with other cinema guests.
                </p>
                <a href="<?= BASE_URL; ?>/login.php" class="btn btn-primary btn-sm">
                    Sign In to Leave a Review
                </a>
            </div>
        <?php elseif (!$isVerifiedBuyer): ?>
            <div style="display: flex; align-items: center; gap: 0.75rem; color: #64748b; font-size: 0.92rem;">
                <span style="font-size: 1.5rem;">🔒</span>
                <div>
                    <strong style="color: var(--c-ink); display: block;">Purchase Verification Required</strong>
                    <span>Only guests who have completed a booking for this movie may submit a review. Book a ticket above to unlock reviews!</span>
                </div>
            </div>
        <?php else: ?>
            <!-- Verified Customer Submission / Edit Form -->
            <div>
                <h3 style="font-size: 1.05rem; margin: 0 0 0.5rem; color: var(--c-ink);">
                    <?= $userExistingReview ? '✏️ Edit Your Verified Review' : '✍️ Share Your Verified Movie Review'; ?>
                </h3>
                <p style="color: var(--c-muted); font-size: 0.82rem; margin: 0 0 1rem;">
                    Verified Customer Badge: You purchased a ticket for this movie. Your review will be filtered for profanity before publication.
                </p>

                <form method="POST" action="<?= BASE_URL; ?>/review_action.php">
                    <input type="hidden" name="action" value="<?= $userExistingReview ? 'update' : 'create'; ?>">
                    <input type="hidden" name="item_type" value="movie">
                    <input type="hidden" name="item_id" value="<?= $movieId; ?>">
                    <input type="hidden" name="review_id" value="<?= (int)($userExistingReview['review_id'] ?? 0); ?>">
                    <input type="hidden" name="redirect" value="<?= BASE_URL . '/movie_details.php?id=' . $movieId . '#reviews'; ?>">

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="rating" style="font-weight: 600; font-size: 0.88rem;">Your Star Rating</label>
                        <select name="rating" id="rating" class="form-control" style="width: auto; min-width: 180px; font-weight: 700; color: #b45309;" required>
                            <?php $currRating = (int)($userExistingReview['rating'] ?? 5); ?>
                            <option value="5" <?= $currRating === 5 ? 'selected' : ''; ?>>★★★★★ (5 Stars - Masterpiece)</option>
                            <option value="4" <?= $currRating === 4 ? 'selected' : ''; ?>>★★★★☆ (4 Stars - Great)</option>
                            <option value="3" <?= $currRating === 3 ? 'selected' : ''; ?>>★★★☆☆ (3 Stars - Average)</option>
                            <option value="2" <?= $currRating === 2 ? 'selected' : ''; ?>>★★☆☆☆ (2 Stars - Disappointing)</option>
                            <option value="1" <?= $currRating === 1 ? 'selected' : ''; ?>>★☆☆☆☆ (1 Star - Poor)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="review_text" style="font-weight: 600; font-size: 0.88rem;">Your Review &amp; Commentary</label>
                        <textarea name="review_text" 
                                  id="review_text" 
                                  rows="4" 
                                  class="form-control" 
                                  required 
                                  minlength="5" 
                                  maxlength="2000" 
                                  placeholder="What did you think of the cinematography, sound design, and performances? (Min. 5 characters)"><?= sanitize($userExistingReview['review_text'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="font-weight: 700;">
                        <?= $userExistingReview ? 'Update My Review &rarr;' : 'Publish Verified Review &rarr;'; ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reviews List Display -->
    <div>
        <h3 style="font-size: 1.1rem; color: var(--c-ink); margin: 0 0 1rem;">Guest Reviews (<?= count($reviews); ?>)</h3>
        
        <?php if (!empty($reviews)): ?>
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <?php foreach ($reviews as $rev): 
                    $stars = (int)$rev['rating'];
                    $isAuthor = ($isUserLoggedIn && (int)$rev['user_id'] === $userId);
                ?>
                    <div style="border-bottom: 1px solid var(--c-line); padding-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <?php if (!empty($rev['avatar_path']) && file_exists(PROJECT_ROOT . '/' . $rev['avatar_path'])): ?>
                                    <img src="<?= BASE_URL . '/' . htmlspecialchars($rev['avatar_path']); ?>" 
                                         alt="Reviewer" 
                                         style="width: 38px; height: 38px; object-fit: cover; border-radius: 50%; border: 1.5px solid var(--c-line);">
                                <?php else: ?>
                                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #e0e7ff; color: #3730a3; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 700;">
                                        <?= htmlspecialchars(mb_strtoupper(mb_substr($rev['full_name'], 0, 1))); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong style="color: var(--c-ink); font-size: 0.95rem; display: block;">
                                        <?= sanitize($rev['full_name']); ?>
                                        <span style="font-size: 0.72rem; background: #dcfce7; color: #166534; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700; margin-left: 0.35rem;">
                                            ✓ Verified Ticket Purchase
                                        </span>
                                    </strong>
                                    <small style="color: var(--c-muted); font-size: 0.78rem;">
                                        <?= date('F j, Y', strtotime($rev['created_at'])); ?>
                                        <?php if (!empty($rev['updated_at']) && $rev['updated_at'] !== $rev['created_at']): ?>
                                            &bull; <em>(edited)</em>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>

                            <div style="color: #f59e0b; font-size: 1.05rem;">
                                <?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars); ?>
                            </div>
                        </div>

                        <p style="color: #334155; font-size: 0.92rem; line-height: 1.5; margin: 0;">
                            <?= nl2br(sanitize($rev['review_text'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--c-muted); font-size: 0.9rem; text-align: center; padding: 2rem; background: #f8fafc; border-radius: 8px;">
                No customer reviews yet. Be the first verified guest to review this title!
            </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
