<?php
/**
 * ScreenBites - Create Concession Product
 * Modeled after itim211-2b-shop/item/create.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Add New Concession Item';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sb-page-header">
    <div>
        <h1>Add New Concession Item</h1>
        <p>Register a snack, beverage, or packaged combo into inventory.</p>
    </div>
    <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">&larr; Back to Concessions</a>
</div>

<div class="sb-card" style="max-width: 650px; margin: 0 auto;">
    <form method="POST" action="<?= BASE_URL; ?>/concessions/store.php" enctype="multipart/form-data">
        <div class="form-group">
            <label for="product_name">Item Description / Name *</label>
            <input type="text" id="product_name" name="product_name" class="form-control" required placeholder="e.g. Caramel Popcorn (Large)">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" class="form-control" required>
                    <option value="Popcorn">Popcorn</option>
                    <option value="Beverage">Beverage</option>
                    <option value="Snacks">Snacks</option>
                    <option value="Candy">Candy</option>
                </select>
            </div>

            <div class="form-group">
                <label for="current_stock">Initial Stock Quantity *</label>
                <input type="number" id="current_stock" name="current_stock" class="form-control" min="0" value="50" required>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="retail_price">Selling Price (PHP) *</label>
                <input type="number" step="0.01" id="retail_price" name="retail_price" class="form-control" min="0.01" required placeholder="e.g. 150.00">
            </div>

            <div class="form-group">
                <label for="unit_cost">Cost Price (PHP)</label>
                <input type="number" step="0.01" id="unit_cost" name="unit_cost" class="form-control" min="0" value="50.00" placeholder="e.g. 60.00">
            </div>
        </div>



        <div class="form-group">
            <label for="image_file">Item Image (Placed in uploads/products/)</label>
            <input type="file" id="image_file" name="image_file" class="form-control" accept="image/*">
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Concession Item</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
