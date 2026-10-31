<?php
/**
 * ScreenBites - Concessions & Bundles Catalog
 * Procedural module modeled after PedalWorks-Dynamics
 */

require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Concessions & Value Combos';
require_once dirname(__DIR__) . '/includes/header.php';

// Category filter
$category = $_GET['category'] ?? '';

// 1. Fetch individual products (if not filtering exclusively for Combos)
$products = [];
if ($category !== 'Combos') {
    $where = "WHERE is_active = 1";
    if (!empty($category)) {
        $catSafe = mysqli_real_escape_string($conn, $category);
        $where .= " AND category = '{$catSafe}'";
    }
    $pSql = "SELECT * FROM products {$where} ORDER BY category ASC, product_name ASC";
    $pRes = mysqli_query($conn, $pSql);
    if ($pRes) {
        while ($p = mysqli_fetch_assoc($pRes)) {
            $products[] = $p;
        }
    }
}

// 2. Fetch combos (if filtering All or Combos)
$combos = [];
if (empty($category) || $category === 'Combos') {
    $cSql = "SELECT * FROM combos WHERE is_active = 1 ORDER BY combo_price ASC";
    $cRes = mysqli_query($conn, $cSql);
    if ($cRes) {
        while ($c = mysqli_fetch_assoc($cRes)) {
            $cId = (int)$c['combo_id'];
            // Fetch constituent items with product current stock
            $ciSql = "SELECT ci.quantity, p.product_name, p.current_stock
                      FROM combo_items ci
                      INNER JOIN products p ON ci.product_id = p.product_id
                      WHERE ci.combo_id = {$cId}";
            $ciRes = mysqli_query($conn, $ciSql);
            $itemsList = [];
            $maxAvailable = 9999;
            while ($child = mysqli_fetch_assoc($ciRes)) {
                $needed = (int)$child['quantity'];
                $stock  = (int)$child['current_stock'];
                $itemsList[] = "{$needed}x " . $child['product_name'];
                $possible = ($needed > 0) ? intdiv($stock, $needed) : 9999;
                if ($possible < $maxAvailable) {
                    $maxAvailable = $possible;
                }
            }
            $c['items_list'] = $itemsList;
            $c['max_available'] = $maxAvailable;
            $combos[] = $c;
        }
    }
}

// Distinct product categories for filter bar
$catRes = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category ASC");
$catIcons = ['Popcorn' => '🍿', 'Beverage' => '🥤', 'Snacks' => '🌭', 'Candy' => '🍫'];
?>

<div class="sb-page-header">
    <div>
        <h1>Snacks &amp; Concessions</h1>
        <p>Browse fresh popcorn, ice-cold beverages, and value-packed combo bundles.</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <?php if (is_admin()): ?>
            <a href="<?= BASE_URL; ?>/concessions/combos.php" class="btn btn-secondary" style="border: 1px solid rgba(245, 158, 11, 0.4); color: #f59e0b;">
                🎁 Manage Combos
            </a>
            <a href="<?= BASE_URL; ?>/concessions/create.php" class="btn btn-primary">
                + Add Concession
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL; ?>/view_cart.php" class="btn btn-secondary">View Cart</a>
    </div>
</div>

<!-- CATEGORY FILTER BAR -->
<div class="sb-card" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; padding: 0.75rem 1.25rem;">
    <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">Filter:</span>
    <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-sm <?= empty($category) ? 'btn-primary' : 'btn-secondary'; ?>">All Concessions</a>
    <a href="<?= BASE_URL; ?>/concessions/index.php?category=Combos" class="btn btn-sm <?= ($category === 'Combos') ? 'btn-primary' : 'btn-secondary'; ?>" style="border: 1px solid rgba(245, 158, 11, 0.4);">
        🎁 Value Combos (<?= count($combos); ?>)
    </a>
    <?php while ($c = mysqli_fetch_assoc($catRes)): ?>
        <a href="<?= BASE_URL; ?>/concessions/index.php?category=<?= urlencode($c['category']); ?>" 
           class="btn btn-sm <?= ($category === $c['category']) ? 'btn-primary' : 'btn-secondary'; ?>">
            <?= sanitize($c['category']); ?>
        </a>
    <?php endwhile; ?>
</div>

<!-- UNIFIED CONCESSIONS & COMBOS GRID -->
<div class="sb-grid">

    <!-- 1. RENDER VALUE COMBOS FIRST AS PROMINENT CARDS (If in All or Combos tab) -->
    <?php foreach ($combos as $combo): 
        $cId = (int)$combo['combo_id'];
        $canFulfill = ($combo['max_available'] > 0);
    ?>
        <div class="sb-item-card" style="border: 1px solid rgba(245, 158, 11, 0.35); background: linear-gradient(180deg, rgba(245, 158, 11, 0.04) 0%, rgba(17, 24, 39, 0.8) 100%);">
            <form method="POST" action="<?= BASE_URL; ?>/cart_update.php" style="display: flex; flex-direction: column; height: 100%;">
                <input type="hidden" name="type" value="add_combo">
                <input type="hidden" name="combo_id" value="<?= $cId; ?>">

                <div class="sb-item-thumb" style="display: flex; align-items: center; justify-content: center; background: radial-gradient(circle, rgba(245, 158, 11, 0.15) 0%, rgba(15, 17, 26, 0.9) 70%); font-size: 3.5rem; position: relative;">
                    <span>🎁</span>
                    <span style="position: absolute; top: 10px; right: 10px; background: #f59e0b; color: #000; font-weight: 800; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Value Bundle
                    </span>
                </div>

                <div class="sb-item-body">
                    <h3 class="sb-item-title" style="color: #fbbf24;"><?= sanitize($combo['combo_name']); ?></h3>
                    
                    <p class="sb-item-meta" style="margin-bottom: 0.35rem;">
                        <span style="color: <?= $canFulfill ? '#10b981' : '#ef4444'; ?>; font-weight: 600;">
                            <?= $canFulfill ? "Available ({$combo['max_available']} bundles)" : "Temporarily Sold Out"; ?>
                        </span>
                    </p>

                    <div style="background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 6px; padding: 0.45rem 0.65rem; margin-bottom: 0.75rem; font-size: 0.82rem; color: #1e293b;">
                        <strong style="display: block; color: #b45309; font-size: 0.75rem; margin-bottom: 0.2rem; text-transform: uppercase;">Includes:</strong>
                        <span style="color: #334155; font-weight: 600;"><?= implode(' + ', array_map('sanitize', $combo['items_list'])); ?></span>
                    </div>

                    <div class="sb-item-price" style="color: #f59e0b;"><?= format_currency($combo['combo_price']); ?></div>

                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: auto;">
                        <?php if ($canFulfill): ?>
                            <input type="number" name="quantity" value="1" min="1" max="<?= $combo['max_available']; ?>" class="form-control" style="width: 70px; text-align: center;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; background: #d97706; border-color: #d97706;">+ Add Bundle</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary" style="flex: 1; cursor: not-allowed;" disabled>Sold Out</button>
                        <?php endif; ?>
                    </div>

                    <?php if (is_admin()): ?>
                        <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(255,255,255,0.08); display: flex; gap: 0.5rem;">
                            <a href="<?= BASE_URL; ?>/concessions/edit_combo.php?id=<?= $cId; ?>" class="btn btn-sm btn-secondary" style="flex: 1; text-align: center;">Edit Bundle</a>
                            <a href="<?= BASE_URL; ?>/concessions/delete_combo.php?id=<?= $cId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this combo bundle?');">Delete</a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php endforeach; ?>

    <!-- 2. RENDER STANDALONE CONCESSION PRODUCTS -->
    <?php foreach ($products as $row): 
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
                            <a href="<?= BASE_URL; ?>/concessions/edit.php?id=<?= $pId; ?>" class="btn btn-sm btn-secondary" style="flex: 1; text-align: center;">Edit</a>
                            <a href="<?= BASE_URL; ?>/concessions/delete.php?id=<?= $pId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this snack item?');">Delete</a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php endforeach; ?>

    <?php if (empty($combos) && empty($products)): ?>
        <p style="grid-column: 1 / -1; color: var(--text-muted); text-align: center; padding: 3rem;">No concession products or bundles found in this category.</p>
    <?php endif; ?>

</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
