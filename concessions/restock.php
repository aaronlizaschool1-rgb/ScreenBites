<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Receive Stock / Restock Concession';
require_once dirname(__DIR__) . '/includes/header.php';

$productsQuery = mysqli_query($conn, "SELECT product_id, product_name, category, current_stock, reorder_level, unit_cost FROM products WHERE is_active = 1 ORDER BY category ASC, product_name ASC");

$preselectId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
?>

<div class="sb-page-header">
    <div>
        <h1>📦 Restock Inventory</h1>
        <p>Record stock arrival, supplier invoice details, and replenishment costs.</p>
    </div>
    <div>
        <a href="<?= BASE_URL; ?>/concessions/restock_history.php" class="btn btn-secondary">
            📋 Restock History
        </a>
        <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">
            Back to Concessions
        </a>
    </div>
</div>

<div class="sb-card" style="max-width: 720px; margin: 0 auto;">
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.25rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0.75rem;">
        Stock Receiving Slip
    </h2>

    <form action="<?= BASE_URL; ?>/concessions/store_restock.php" method="POST">
        <div style="margin-bottom: 1.25rem;">
            <label for="product_id" style="display: block; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.9rem;">
                Select Concession Item <span style="color: #ef4444;">*</span>
            </label>
            <select name="product_id" id="product_id" required
                    style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit; font-size: 0.95rem;">
                <option value="">-- Select Product to Replenish --</option>
                <?php while ($p = mysqli_fetch_assoc($productsQuery)): ?>
                    <option value="<?= $p['product_id']; ?>"
                            data-cost="<?= $p['unit_cost']; ?>"
                            data-stock="<?= $p['current_stock']; ?>"
                            <?= ($preselectId === (int)$p['product_id']) ? 'selected' : ''; ?>>
                        [<?= sanitize($p['category']); ?>] <?= sanitize($p['product_name']); ?>
                        (Current Stock: <?= (int)$p['current_stock']; ?>, Catalog Cost: ₱<?= number_format($p['unit_cost'], 2); ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
                <label for="quantity_added" style="display: block; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.9rem;">
                    Quantity Added <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" name="quantity_added" id="quantity_added" min="1" step="1" required placeholder="e.g. 50"
                       style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit; font-size: 0.95rem;">
                <span style="font-size: 0.8rem; color: var(--text-muted);">Units to add to current on-hand stock.</span>
            </div>

            <div>
                <label for="purchase_cost" style="display: block; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.9rem;">
                    Batch Unit Cost (₱) <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" name="purchase_cost" id="purchase_cost" min="0" step="0.01" required placeholder="0.00"
                       style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit; font-size: 0.95rem;">
                <span style="font-size: 0.8rem; color: var(--text-muted);">Actual supplier acquisition cost per unit for this batch.</span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
                <label for="supplier_name" style="display: block; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.9rem;">
                    Supplier Name <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="supplier_name" id="supplier_name" required placeholder="e.g. San Miguel Foods / Universal Robina"
                       style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit; font-size: 0.95rem;">
            </div>

            <div>
                <label for="invoice_reference" style="display: block; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.9rem;">
                    Invoice / Delivery Slip Ref #
                </label>
                <input type="text" name="invoice_reference" id="invoice_reference" placeholder="e.g. INV-2026-0891"
                       style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit; font-size: 0.95rem;">
            </div>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label for="notes" style="display: block; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.9rem;">
                Remarks / Delivery Notes
            </label>
            <textarea name="notes" id="notes" rows="3" placeholder="Condition of packaging, expiration notes, batch codes, etc."
                      style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit; font-size: 0.95rem;"></textarea>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
            <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600;">
                Confirm &amp; Restock Stock
            </button>
        </div>
    </form>
</div>

<script>

document.getElementById('product_id').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var costInput = document.getElementById('purchase_cost');
    if (opt && opt.getAttribute('data-cost')) {
        if (!costInput.value || costInput.value == '0.00') {
            costInput.value = parseFloat(opt.getAttribute('data-cost')).toFixed(2);
        }
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
