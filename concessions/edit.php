<?php
/**
 * ScreenBites - Edit Concession Item
 * Modeled after itim211-2b-shop/item/edit.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$res = mysqli_query($conn, "SELECT * FROM products WHERE product_id = {$id} LIMIT 1");
$product = mysqli_fetch_assoc($res);

if (!$product) {
    header("Location: " . BASE_URL . "/concessions/index.php");
    exit;
}

$pageTitle = 'Edit ' . $product['product_name'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sb-page-header">
    <div>
        <h1>Edit Concession Item</h1>
        <p>Modify pricing, category, and inventory stock level.</p>
    </div>
    <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">&larr; Back to Concessions</a>
</div>

<div class="sb-card" style="max-width: 650px; margin: 0 auto;">
    <form method="POST" action="<?= BASE_URL; ?>/concessions/update.php" enctype="multipart/form-data">
        <input type="hidden" name="product_id" value="<?= (int)$product['product_id']; ?>">

        <div class="form-group">
            <label for="product_name">Item Description / Name *</label>
            <input type="text" id="product_name" name="product_name" value="<?= sanitize($product['product_name']); ?>" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" class="form-control" required>
                    <?php 
                    $cats = ['Popcorn', 'Beverage', 'Snacks', 'Candy'];
                    foreach ($cats as $cat):
                    ?>
                        <option value="<?= $cat; ?>" <?= ($product['category'] === $cat) ? 'selected' : ''; ?>>
                            <?= $cat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="current_stock">Current Stock Quantity *</label>
                <input type="number" id="current_stock" name="current_stock" value="<?= (int)$product['current_stock']; ?>" class="form-control" min="0" required>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="retail_price">Selling Price (PHP) *</label>
                <input type="number" step="0.01" id="retail_price" name="retail_price" value="<?= (float)$product['retail_price']; ?>" class="form-control" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="unit_cost">Cost Price (PHP)</label>
                <input type="number" step="0.01" id="unit_cost" name="unit_cost" value="<?= (float)$product['unit_cost']; ?>" class="form-control" min="0">
            </div>
        </div>



        <div class="form-group">
            <label for="image_file">Item Image (uploads/products/)</label>
            <?php if (!empty($product['image_path'])): ?>
                <div style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <img src="<?= BASE_URL . '/' . $product['image_path']; ?>" alt="Current image" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid var(--card-border);">
                    <small style="color: var(--text-muted);"><?= sanitize($product['image_path']); ?></small>
                </div>
            <?php endif; ?>
            <input type="file" id="image_file" name="image_file" class="form-control" accept="image/*">
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
