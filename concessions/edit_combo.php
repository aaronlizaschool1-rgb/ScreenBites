<?php
/**
 * ScreenBites - Edit Concession Combo Bundle
 * Procedural module modeled after PedalWorks-Dynamics
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$cRes = mysqli_query($conn, "SELECT * FROM combos WHERE combo_id = {$id} LIMIT 1");
$combo = mysqli_fetch_assoc($cRes);

if (!$combo) {
    set_flash('error', 'Combo record not found.');
    header("Location: " . BASE_URL . "/concessions/combos.php");
    exit;
}

// Fetch existing constituent items
$ciRes = mysqli_query($conn, "SELECT product_id, quantity FROM combo_items WHERE combo_id = {$id}");
$currentItems = [];
while ($ci = mysqli_fetch_assoc($ciRes)) {
    $currentItems[(int)$ci['product_id']] = (int)$ci['quantity'];
}

// Fetch all active products
$pRes = mysqli_query($conn, "SELECT product_id, product_name, category, retail_price, current_stock FROM products WHERE is_active = 1 ORDER BY category ASC, product_name ASC");
$allProducts = [];
while ($p = mysqli_fetch_assoc($pRes)) {
    $allProducts[] = $p;
}

$pageTitle = 'Edit ' . $combo['combo_name'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sb-page-header">
    <div>
        <h1>Edit Concession Combo Bundle</h1>
        <p>Update bundle pricing, constituent products, and availability.</p>
    </div>
    <a href="<?= BASE_URL; ?>/concessions/combos.php" class="btn btn-secondary">&larr; Back to Combos</a>
</div>

<div class="sb-card" style="max-width: 720px; margin: 0 auto;">
    <form method="POST" action="<?= BASE_URL; ?>/concessions/update_combo.php">
        <input type="hidden" name="combo_id" value="<?= (int)$combo['combo_id']; ?>">

        <div class="form-group">
            <label for="combo_name">Bundle / Combo Name *</label>
            <input type="text" id="combo_name" name="combo_name" value="<?= sanitize($combo['combo_name']); ?>" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="combo_price">Bundle Retail Price (₱) *</label>
                <input type="number" step="0.01" id="combo_price" name="combo_price" value="<?= (float)$combo['combo_price']; ?>" class="form-control" min="0" required>
            </div>

            <div class="form-group">
                <label for="is_active">Status</label>
                <select id="is_active" name="is_active" class="form-control">
                    <option value="1" <?= ((int)$combo['is_active'] === 1) ? 'selected' : ''; ?>>Active (Visible in Catalog)</option>
                    <option value="0" <?= ((int)$combo['is_active'] === 0) ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Short Description / Subtitle</label>
            <input type="text" id="description" name="description" value="<?= sanitize($combo['description'] ?? ''); ?>" class="form-control">
        </div>

        <div class="form-group" style="margin-top: 1.5rem;">
            <label style="font-weight: 700; margin-bottom: 0.5rem; display: block;">Select Included Products &amp; Quantities *</label>
            <p style="color: var(--c-muted, #5d6180); font-size: 0.85rem; margin-top: -0.25rem; margin-bottom: 0.75rem;">
                Adjust the quantity of each product included in this bundle (0 removes it from the bundle).
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
                            $existingQty = $currentItems[$pId] ?? 0;
                        ?>
                            <tr style="<?= $existingQty > 0 ? 'background: rgba(245, 158, 11, 0.05);' : ''; ?>">
                                <td>
                                    <strong><?= sanitize($prod['product_name']); ?></strong>
                                    <?php if ($existingQty > 0): ?>
                                        <span style="color: #f59e0b; font-size: 0.75rem; margin-left: 0.35rem;">★ currently included</span>
                                    <?php endif; ?>
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
                                           value="<?= $existingQty; ?>" 
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
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
