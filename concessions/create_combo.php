<?php
/**
 * ScreenBites - Create Concession Combo Bundle
 * Procedural module modeled after PedalWorks-Dynamics
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Create Concession Combo';
require_once dirname(__DIR__) . '/includes/header.php';

// Fetch all active products that can be included in this bundle
$pRes = mysqli_query($conn, "SELECT product_id, product_name, category, retail_price, current_stock FROM products WHERE is_active = 1 ORDER BY category ASC, product_name ASC");
$allProducts = [];
while ($p = mysqli_fetch_assoc($pRes)) {
    $allProducts[] = $p;
}
?>

<div class="sb-page-header">
    <div>
        <h1>Create Concession Combo Bundle</h1>
        <p>Package multiple snacks and beverages together at a discounted combo price.</p>
    </div>
    <a href="<?= BASE_URL; ?>/concessions/combos.php" class="btn btn-secondary">&larr; Back to Combos</a>
</div>

<div class="sb-card" style="max-width: 720px; margin: 0 auto;">
    <form method="POST" action="<?= BASE_URL; ?>/concessions/store_combo.php">
        
        <div class="form-group">
            <label for="combo_name">Bundle / Combo Name *</label>
            <input type="text" id="combo_name" name="combo_name" class="form-control" required placeholder="e.g. Cinema Couples Duo Treat">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="combo_price">Bundle Retail Price (₱) *</label>
                <input type="number" step="0.01" id="combo_price" name="combo_price" class="form-control" min="0" required placeholder="e.g. 299.00">
            </div>

            <div class="form-group">
                <label for="description">Short Description / Subtitle</label>
                <input type="text" id="description" name="description" class="form-control" placeholder="e.g. 2 Large Popcorns + 2 22oz Sodas">
            </div>
        </div>

        <div class="form-group" style="margin-top: 1.5rem;">
            <label style="font-weight: 700; margin-bottom: 0.5rem; display: block;">Select Included Products &amp; Quantities *</label>
            <p style="color: var(--c-muted, #5d6180); font-size: 0.85rem; margin-top: -0.25rem; margin-bottom: 0.75rem;">
                Specify the quantity of each product included in this bundle package (set to 0 for items not included).
            </p>

            <div class="sb-table-wrap" style="border: 1px solid var(--c-line, #dcdff0); border-radius: 6px; max-height: 340px; overflow-y: auto;">
                <table class="sb-table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Retail Price</th>
                            <th>Current Stock</th>
                            <th style="width: 120px; text-align: center;">Qty in Bundle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allProducts as $prod): 
                            $pId = (int)$prod['product_id'];
                        ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($prod['product_name']); ?></strong>
                                </td>
                                <td>
                                    <span style="font-size: 0.82rem; color: var(--c-muted, #5d6180);"><?= sanitize($prod['category']); ?></span>
                                </td>
                                <td><?= format_currency($prod['retail_price']); ?></td>
                                <td>
                                    <span style="color: <?= ((int)$prod['current_stock'] > 0) ? '#10b981' : '#ef4444'; ?>; font-size: 0.85rem;">
                                        <?= (int)$prod['current_stock']; ?> units
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" 
                                           name="items[<?= $pId; ?>]" 
                                           value="0" 
                                           min="0" 
                                           max="20" 
                                           class="form-control" 
                                           style="width: 70px; text-align: center; display: inline-block; padding: 0.25rem;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= BASE_URL; ?>/concessions/combos.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Combo Bundle</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
