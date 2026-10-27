<?php
/**
 * ScreenBites - Concessions Catalog & Management
 * Modeled after itim211-2b-shop/item/index.php
 */

require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Concessions & Snacks';
require_once dirname(__DIR__) . '/includes/header.php';

// Category filter
$category = $_GET['category'] ?? '';
$where = "WHERE is_active = 1";
if (!empty($category)) {
    $catSafe = mysqli_real_escape_string($conn, $category);
    $where .= " AND category = '{$catSafe}'";
}

$sql = "SELECT * FROM products {$where} ORDER BY category ASC, product_name ASC";
$res = mysqli_query($conn, $sql);

// Distinct categories
$catRes = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category ASC");
?>

<div class="sb-page-header">
    <div>
        <h1>Snacks &amp; Concessions</h1>
        <p>Browse tasty movie treats, beverages, and combo items.</p>
    </div>
    <div>
        <?php if (is_admin()): ?>
            <a href="<?= BASE_URL; ?>/concessions/create.php" class="btn btn-primary">+ Add New Concession</a>
        <?php endif; ?>
        <a href="<?= BASE_URL; ?>/view_cart.php" class="btn btn-secondary">View Cart</a>
    </div>
</div>

<div class="sb-card" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; padding: 0.75rem 1.25rem;">
    <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">Filter:</span>
    <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-sm <?= empty($category) ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
    <?php while ($c = mysqli_fetch_assoc($catRes)): ?>
        <a href="<?= BASE_URL; ?>/concessions/index.php?category=<?= urlencode($c['category']); ?>" 
           class="btn btn-sm <?= ($category === $c['category']) ? 'btn-primary' : 'btn-secondary'; ?>">
            <?= sanitize($c['category']); ?>
        </a>
    <?php endwhile; ?>
</div>

<div class="sb-grid">
    <?php if ($res && mysqli_num_rows($res) > 0): ?>
        <?php 
        $catIcons = ['Popcorn' => '🍿', 'Beverage' => '🥤', 'Snacks' => '🌭', 'Candy' => '🍫'];
        while ($row = mysqli_fetch_assoc($res)): 
            $pId = (int)$row['product_id'];
            $hasImg = !empty($row['image_path']) && file_exists(dirname(__DIR__) . '/' . $row['image_path']);
            $icon = $catIcons[$row['category']] ?? '🍿';
            $inStock = (int)$row['current_stock'] > 0;
        ?>
            <div class="sb-item-card">
                <form method="POST" action="<?= BASE_URL; ?>/cart_update.php" style="display: flex; flex-direction: column; height: 100%;">
                    <input type="hidden" name="type" value="add_concession">
                    <input type="hidden" name="product_id" value="<?= $pId; ?>">

                    <?php if ($hasImg): ?>
                        <img src="<?= BASE_URL . '/' . $row['image_path']; ?>" alt="<?= sanitize($row['product_name']); ?>" class="sb-item-thumb">
                    <?php else: ?>
                        <div class="sb-item-thumb" style="display: flex; align-items: center; justify-content: center; background: #141724; font-size: 3.5rem;">
                            <span><?= $icon; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="sb-item-body">
                        <h3 class="sb-item-title"><?= sanitize($row['product_name']); ?></h3>
                        <p class="sb-item-meta">
                            <?= sanitize($row['category']); ?> &bull; 
                            <span style="color: <?= $inStock ? '#10b981' : '#ef4444'; ?>;">
                                <?= $inStock ? 'In Stock (' . (int)$row['current_stock'] . ')' : 'Out of Stock'; ?>
                            </span>
                        </p>

                        <div class="sb-item-price"><?= format_currency($row['retail_price']); ?></div>

                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: auto;">
                            <?php if ($inStock): ?>
                                <input type="number" name="quantity" value="1" min="1" max="<?= (int)$row['current_stock']; ?>" class="form-control" style="width: 70px; text-align: center;">
                                <button type="submit" class="btn btn-primary" style="flex: 1;">+ Add</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary" style="flex: 1; cursor: not-allowed;" disabled>Sold Out</button>
                            <?php endif; ?>
                        </div>

                        <?php if (is_admin()): ?>
                            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--card-border); display: flex; gap: 0.5rem;">
                                <a href="<?= BASE_URL; ?>/concessions/edit.php?id=<?= $pId; ?>" class="btn btn-sm btn-secondary" style="flex: 1;">Edit</a>
                                <a href="<?= BASE_URL; ?>/concessions/delete.php?id=<?= $pId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this snack item?');">Delete</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="color: var(--text-muted);">No concession products found in this category.</p>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
