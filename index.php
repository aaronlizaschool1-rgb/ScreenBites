<?php
/**
 * ScreenBites - Cinema Box Office Catalog & Home
 * Modeled after itim211-2b-shop/index.php
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Now Showing Cinema & Lounge';
require_once __DIR__ . '/includes/header.php';

// Fetch active movies with their upcoming screenings
$sqlMovies = "SELECT * FROM movies WHERE is_active = 1 ORDER BY movie_id DESC";
$resMovies = mysqli_query($conn, $sqlMovies);

// Fetch concessions for quick add-to-cart
$sqlSnacks = "SELECT * FROM products WHERE is_active = 1 ORDER BY category ASC, product_name ASC LIMIT 8";
$resSnacks = mysqli_query($conn, $sqlSnacks);
?>

<div class="sb-page-header">
    <div>
        <h1>Now Showing in Theaters</h1>
        <p>Book cinema tickets and reserve your VIP seats online.</p>
    </div>
    <?php if (is_admin()): ?>
        <div>
            <a href="<?= BASE_URL; ?>/movies/create.php" class="btn btn-primary">+ Add New Movie</a>
            <a href="<?= BASE_URL; ?>/schedules/create.php" class="btn btn-secondary">+ Schedule Screening</a>
        </div>
    <?php endif; ?>
</div>

<!-- Movies Section -->
<div class="sb-grid" style="margin-bottom: 3rem;">
    <?php if ($resMovies && mysqli_num_rows($resMovies) > 0): ?>
        <?php while ($movie = mysqli_fetch_assoc($resMovies)): 
            $mId = (int)$movie['movie_id'];
            $posterPath = !empty($movie['poster_path']) ? BASE_URL . '/' . $movie['poster_path'] : BASE_URL . '/assets/img/default-poster.jpg';

            // Fetch screenings for this movie
            $scSql = "SELECT s.screening_id, s.screening_date, s.start_time, s.base_price, h.hall_name 
                      FROM screenings s
                      INNER JOIN halls h ON s.hall_id = h.hall_id
                      WHERE s.movie_id = {$mId}
                      ORDER BY s.screening_date ASC, s.start_time ASC";
            $scRes = mysqli_query($conn, $scSql);
        ?>
            <div class="sb-item-card">
                <img src="<?= $posterPath; ?>" alt="<?= sanitize($movie['title']); ?>" class="sb-item-thumb">
                <div class="sb-item-body">
                    <h3 class="sb-item-title"><?= sanitize($movie['title']); ?></h3>
                    <div class="sb-item-meta">
                        <span style="border: 1px solid var(--card-border); padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 600;">
                            <?= sanitize($movie['rating'] ?? 'PG'); ?>
                        </span>
                        &bull; <?= (int)$movie['duration_minutes']; ?> mins
                    </div>

                    <div style="margin-top: 0.5rem; margin-bottom: 1rem;">
                        <strong style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;">
                            AVAILABLE SCREENINGS:
                        </strong>

                        <?php if ($scRes && mysqli_num_rows($scRes) > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                                <?php while ($sc = mysqli_fetch_assoc($scRes)): 
                                    $timeLabel = date('M d, g:i A', strtotime($sc['screening_date'] . ' ' . $sc['start_time']));
                                ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: #0f111a; padding: 0.4rem 0.6rem; border-radius: 4px; border: 1px solid var(--card-border); font-size: 0.85rem;">
                                        <div>
                                            <strong><?= $timeLabel; ?></strong><br>
                                            <small style="color: var(--text-muted);"><?= sanitize($sc['hall_name']); ?></small>
                                        </div>
                                        <div style="text-align: right;">
                                            <span style="color: var(--accent-gold); font-weight: 700;"><?= format_currency($sc['base_price']); ?></span><br>
                                            <a href="<?= BASE_URL; ?>/ticketing/seat_matrix.php?screening_id=<?= (int)$sc['screening_id']; ?>" 
                                               class="btn btn-sm btn-primary" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; margin-top: 0.2rem;">
                                                Select Seats &rarr;
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">No upcoming showtimes scheduled.</p>
                        <?php endif; ?>
                    </div>

                    <?php if (is_admin()): ?>
                        <div style="margin-top: auto; padding-top: 0.75rem; border-top: 1px solid var(--card-border); display: flex; gap: 0.5rem;">
                            <a href="<?= BASE_URL; ?>/movies/edit.php?id=<?= $mId; ?>" class="btn btn-sm btn-secondary" style="flex: 1;">Edit</a>
                            <a href="<?= BASE_URL; ?>/movies/delete.php?id=<?= $mId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this movie?');">Delete</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="color: var(--text-muted);">No movies currently in catalog.</p>
    <?php endif; ?>
</div>

<!-- Concessions Section -->
<div class="sb-page-header">
    <div>
        <h2>Snacks &amp; Concessions</h2>
        <p>Pair your movie with freshly popped popcorn and beverages.</p>
    </div>
    <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">View All Snacks &rarr;</a>
</div>

<div class="sb-grid">
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

                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: auto;">
                            <input type="number" name="quantity" value="1" min="1" max="<?= (int)$snack['current_stock']; ?>" class="form-control" style="width: 70px; text-align: center;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">+ Add</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
