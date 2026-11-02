<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/reviews.php';

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    header("Location: " . BASE_URL . "/concessions/index.php");
    exit;
}

$pStmt = mysqli_prepare($conn, "SELECT product_id, product_name, category, unit_cost, retail_price, current_stock, image_path, is_active FROM products WHERE product_id = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($pStmt, "i", $productId);
mysqli_stmt_execute($pStmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($pStmt));
mysqli_stmt_close($pStmt);

if (!$product) {
    set_flash('error', 'Product not found or is currently inactive.');
    header("Location: " . BASE_URL . "/concessions/index.php");
    exit;
}

$reviews = get_item_reviews($conn, 'concession', $productId);
$reviewSummary = get_item_review_summary($conn, 'concession', $productId);

$isUserLoggedIn = is_logged_in();
$userId = $isUserLoggedIn ? (int)current_user()['user_id'] : 0;
$isVerifiedBuyer = $isUserLoggedIn ? has_user_purchased_item($conn, $userId, 'concession', $productId) : false;
$userExistingReview = ($isUserLoggedIn && $isVerifiedBuyer) ? get_user_item_review($conn, $userId, 'concession', $productId) : null;

$inStock = (int)$product['current_stock'] > 0;
$catIcons = ['Popcorn' => '🍿', 'Beverage' => '🥤', 'Snacks' => '🌭', 'Candy' => '🍫'];
$icon = $catIcons[$product['category']] ?? '🍿';

$pageTitle = $product['product_name'] . ' - Concessions & Reviews';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="margin-bottom: 1.25rem;">
    <a href="<?= BASE_URL; ?>/concessions/index.php" style="color: var(--c-muted); text-decoration: none; font-size: 0.88rem; font-weight: 500;">
        &larr; Back to Concessions Lounge
    </a>
</div>

<div class="card" style="padding: 2rem; margin-bottom: 2rem;">
    <div style="display: grid; grid-template-columns: 240px 1fr; gap: 2rem; align-items: start;" class="movie-details-layout">

        <div>
            <?php if (!empty($product['image_path']) && file_exists(dirname(__DIR__) . '/' . $product['image_path'])): ?>
                <img src="<?= BASE_URL . '/' . htmlspecialchars($product['image_path']); ?>"
                     alt="<?= sanitize($product['product_name']); ?>"
                     style="width: 100%; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); object-fit: cover; aspect-ratio: 1/1;">
            <?php else: ?>
                <div style="width: 100%; aspect-ratio: 1/1; border-radius: 12px; background: #141724; display: flex; align-items: center; justify-content: center; font-size: 5rem;">
                    <span><?= $icon; ?></span>
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 1rem; background: #f8fafc; border: 1px solid var(--c-line); padding: 0.75rem; border-radius: 8px;">
                <div style="color: #f59e0b; font-size: 1.35rem; font-weight: 800;">
                    ★ <?= number_format($reviewSummary['average'], 1); ?> <span style="font-size: 0.85rem; color: var(--c-muted); font-weight: 500;">/ 5.0</span>
                </div>
                <small style="color: var(--c-muted); font-size: 0.8rem;">
                    Based on <?= number_format($reviewSummary['count']); ?> verified customer review<?= $reviewSummary['count'] === 1 ? '' : 's'; ?>
                </small>
            </div>
        </div>

        <div>
            <span class="badge" style="background: var(--c-indigo); color: #fff; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.8rem;">
                <?= sanitize($product['category']); ?>
            </span>

            <h1 style="font-size: 2rem; margin: 0.5rem 0; color: var(--c-ink); line-height: 1.2;">
                <?= sanitize($product['product_name']); ?>
            </h1>

            <div style="font-size: 1.6rem; font-weight: 800; color: var(--accent-gold, #f59e0b); margin-bottom: 1rem;">
                <?= format_currency($product['retail_price']); ?>
            </div>

            <p style="color: var(--c-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">
                Status: <strong style="color: <?= $inStock ? '#10b981' : '#ef4444'; ?>;"><?= $inStock ? 'In Stock (' . (int)$product['current_stock'] . ' available)' : 'Sold Out'; ?></strong>
            </p>

            <form method="POST" action="<?= BASE_URL; ?>/cart_update.php" style="display: flex; gap: 0.75rem; align-items: center; max-width: 320px;">
                <input type="hidden" name="type" value="add_concession">
                <input type="hidden" name="product_id" value="<?= $productId; ?>">

                <?php if ($inStock): ?>
                    <input type="number" name="quantity" value="1" min="1" max="<?= (int)$product['current_stock']; ?>" class="form-control" style="width: 80px; text-align: center; font-weight: 700;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; font-weight: 700;">
                        Add to Order Cart &rarr;
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary" style="flex: 1; cursor: not-allowed;" disabled>
                        Temporarily Out of Stock
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<div class="card" id="reviews" style="padding: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--c-line); padding-bottom: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.35rem; margin: 0; color: var(--c-ink);">Verified Customer Reviews</h2>
            <p style="margin: 0.25rem 0 0; color: var(--c-muted); font-size: 0.85rem;">
                Reviews from cinema guests who purchased this snack concession.
            </p>
        </div>
        <div style="text-align: right;">
            <span style="font-size: 1.4rem; color: #f59e0b; font-weight: 800;">★ <?= number_format($reviewSummary['average'], 1); ?></span>
            <small style="color: var(--c-muted); display: block; font-size: 0.78rem;"><?= $reviewSummary['count']; ?> review<?= $reviewSummary['count'] === 1 ? '' : 's'; ?></small>
        </div>
    </div>

    <div style="background: #f8fafc; border: 1px solid var(--c-line); border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
        <?php if (!$isUserLoggedIn): ?>
            <div style="text-align: center; padding: 1rem;">
                <p style="color: var(--c-muted); margin-bottom: 0.75rem; font-size: 0.95rem;">
                    Sign in to share your snack review.
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
                    <span>Only guests who have purchased this item in a completed order may submit a review. Add it to your cart above to unlock reviews!</span>
                </div>
            </div>
        <?php else: ?>

            <div>
                <h3 style="font-size: 1.05rem; margin: 0 0 0.5rem; color: var(--c-ink);">
                    <?= $userExistingReview ? '✏️ Edit Your Verified Snack Review' : '✍️ Share Your Verified Snack Review'; ?>
                </h3>
                <p style="color: var(--c-muted); font-size: 0.82rem; margin: 0 0 1rem;">
                    Verified Customer Badge: You purchased this concession item. Your review will be filtered for profanity before publication.
                </p>

                <form method="POST" action="<?= BASE_URL; ?>/review_action.php">
                    <input type="hidden" name="action" value="<?= $userExistingReview ? 'update' : 'create'; ?>">
                    <input type="hidden" name="item_type" value="concession">
                    <input type="hidden" name="item_id" value="<?= $productId; ?>">
                    <input type="hidden" name="review_id" value="<?= (int)($userExistingReview['review_id'] ?? 0); ?>">
                    <input type="hidden" name="redirect" value="<?= BASE_URL . '/concessions/details.php?id=' . $productId . '#reviews'; ?>">

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="rating" style="font-weight: 600; font-size: 0.88rem;">Your Star Rating</label>
                        <select name="rating" id="rating" class="form-control" style="width: auto; min-width: 180px; font-weight: 700; color: #b45309;" required>
                            <?php $currRating = (int)($userExistingReview['rating'] ?? 5); ?>
                            <option value="5" <?= $currRating === 5 ? 'selected' : ''; ?>>★★★★★ (5 Stars - Delicious)</option>
                            <option value="4" <?= $currRating === 4 ? 'selected' : ''; ?>>★★★★☆ (4 Stars - Great)</option>
                            <option value="3" <?= $currRating === 3 ? 'selected' : ''; ?>>★★★☆☆ (3 Stars - Average)</option>
                            <option value="2" <?= $currRating === 2 ? 'selected' : ''; ?>>★★☆☆☆ (2 Stars - Disappointing)</option>
                            <option value="1" <?= $currRating === 1 ? 'selected' : ''; ?>>★☆☆☆☆ (1 Star - Poor)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="review_text" style="font-weight: 600; font-size: 0.88rem;">Your Review &amp; Feedback</label>
                        <textarea name="review_text"
                                  id="review_text"
                                  rows="4"
                                  class="form-control"
                                  required
                                  minlength="5"
                                  maxlength="2000"
                                  placeholder="How was the flavor, portion size, and temperature? (Min. 5 characters)"><?= sanitize($userExistingReview['review_text'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="font-weight: 700;">
                        <?= $userExistingReview ? 'Update My Review &rarr;' : 'Publish Verified Review &rarr;'; ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <h3 style="font-size: 1.1rem; color: var(--c-ink); margin: 0 0 1rem;">Guest Reviews (<?= count($reviews); ?>)</h3>

        <?php if (!empty($reviews)): ?>
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <?php foreach ($reviews as $rev):
                    $stars = (int)$rev['rating'];
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
                                            ✓ Verified Snack Purchase
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
                No customer reviews yet. Be the first verified guest to review this snack!
            </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
