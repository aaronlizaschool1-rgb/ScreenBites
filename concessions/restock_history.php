<?php
/**
 * ScreenBites - Concessions Restock History Ledger
 * Procedural module modeled after PedalWorks-Dynamics
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Stock Receiving Ledger & Restock History';
require_once dirname(__DIR__) . '/includes/header.php';

// Filter by product or supplier
$filterProduct = (int)($_GET['product_id'] ?? 0);
$filterSupplier = trim($_GET['supplier'] ?? '');

$where = "WHERE 1=1";
if ($filterProduct > 0) {
    $where .= " AND r.product_id = {$filterProduct}";
}
if (!empty($filterSupplier)) {
    $supEsc = mysqli_real_escape_string($conn, $filterSupplier);
    $where .= " AND r.supplier_name LIKE '%{$supEsc}%'";
}

$historySql = "SELECT r.*, p.product_name, p.category, p.current_stock, u.full_name AS receiver_name
               FROM stock_restocks r
               INNER JOIN products p ON r.product_id = p.product_id
               LEFT JOIN users u ON r.received_by = u.user_id
               {$where}
               ORDER BY r.created_at DESC, r.restock_id DESC";
$historyRes = mysqli_query($conn, $historySql);

// Fetch products for filter dropdown
$productsList = mysqli_query($conn, "SELECT product_id, product_name FROM products ORDER BY product_name ASC");

// Totals
$totalsSql = "SELECT SUM(quantity_added) AS total_units, SUM(quantity_added * purchase_cost) AS total_spend FROM stock_restocks r {$where}";
$totalsRes = mysqli_query($conn, $totalsSql);
$totals = mysqli_fetch_assoc($totalsRes) ?: ['total_units' => 0, 'total_spend' => 0];
?>

<div class="sb-page-header">
    <div>
        <h1>📋 Stock Replenishment History</h1>
        <p>Complete receiving log of supplier deliveries, unit purchase costs, and stock updates.</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= BASE_URL; ?>/concessions/restock.php" class="btn btn-primary">
            + Receive New Stock
        </a>
        <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">
            Manage Concessions
        </a>
    </div>
</div>

<!-- KPI CARDS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="sb-card" style="border-left: 4px solid var(--accent-gold);">
        <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Filtered Units Received</div>
        <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem;">
            <?= number_format((int)$totals['total_units']); ?> units
        </div>
    </div>
    <div class="sb-card" style="border-left: 4px solid #10b981;">
        <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Filtered Acquisition Cost</div>
        <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: #10b981;">
            ₱<?= number_format((float)$totals['total_spend'], 2); ?>
        </div>
    </div>
</div>

<!-- FILTER BAR -->
<div class="sb-card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
    <form method="GET" action="<?= BASE_URL; ?>/concessions/restock_history.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 220px;">
            <label style="display: block; font-size: 0.85rem; margin-bottom: 0.35rem; color: var(--text-muted); font-weight: 600;">Filter by Product</label>
            <select name="product_id" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit;">
                <option value="">-- All Products --</option>
                <?php while ($prod = mysqli_fetch_assoc($productsList)): ?>
                    <option value="<?= $prod['product_id']; ?>" <?= ($filterProduct === (int)$prod['product_id']) ? 'selected' : ''; ?>>
                        <?= sanitize($prod['product_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; font-size: 0.85rem; margin-bottom: 0.35rem; color: var(--text-muted); font-weight: 600;">Supplier Name</label>
            <input type="text" name="supplier" value="<?= sanitize($filterSupplier); ?>" placeholder="Search supplier..."
                   style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit;">
        </div>

        <div>
            <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1.25rem;">Filter</button>
            <?php if ($filterProduct > 0 || !empty($filterSupplier)): ?>
                <a href="<?= BASE_URL; ?>/concessions/restock_history.php" class="btn btn-secondary" style="padding: 0.55rem 1rem;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- RESTOCK LEDGER TABLE -->
<div class="sb-card" style="padding: 0; overflow-x: auto;">
    <table class="sb-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: rgba(0,0,0,0.2); text-align: left; border-bottom: 1px solid var(--card-border);">
                <th style="padding: 0.75rem 1rem;">Date &amp; Time</th>
                <th style="padding: 0.75rem 1rem;">Product</th>
                <th style="padding: 0.75rem 1rem; text-align: center;">Qty Added</th>
                <th style="padding: 0.75rem 1rem; text-align: right;">Unit Purchase Cost</th>
                <th style="padding: 0.75rem 1rem; text-align: right;">Batch Total Cost</th>
                <th style="padding: 0.75rem 1rem;">Supplier</th>
                <th style="padding: 0.75rem 1rem;">Invoice #</th>
                <th style="padding: 0.75rem 1rem;">Received By</th>
                <th style="padding: 0.75rem 1rem;">Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($historyRes) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($historyRes)): 
                    $batchTotal = (float)$row['quantity_added'] * (float)$row['purchase_cost'];
                ?>
                    <tr style="border-bottom: 1px solid var(--card-border);">
                        <td style="padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted); white-space: nowrap;">
                            <?= date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; font-weight: 600;">
                            <span style="font-size: 0.8rem; background: rgba(255,255,255,0.06); padding: 0.15rem 0.4rem; border-radius: 4px; margin-right: 0.35rem; color: var(--text-muted);">
                                <?= sanitize($row['category']); ?>
                            </span>
                            <?= sanitize($row['product_name']); ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 700; color: #10b981;">
                            +<?= number_format((int)$row['quantity_added']); ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; text-align: right;">
                            ₱<?= number_format((float)$row['purchase_cost'], 2); ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 600; color: var(--accent-gold);">
                            ₱<?= number_format($batchTotal, 2); ?>
                        </td>
                        <td style="padding: 0.75rem 1rem;">
                            <?= sanitize($row['supplier_name']); ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; font-family: monospace; font-size: 0.85rem; color: var(--text-muted);">
                            <?= sanitize($row['invoice_reference'] ?: '—'); ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; font-size: 0.85rem;">
                            <?= sanitize($row['receiver_name'] ?: 'System'); ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted); max-width: 200px;">
                            <?= sanitize($row['notes'] ?: '—'); ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="padding: 2.5rem 1rem; text-align: center; color: var(--text-muted);">
                        No replenishment records found matching your criteria.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
