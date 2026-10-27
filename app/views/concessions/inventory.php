<?php

use Core\Auth;

$e = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$peso = "\u{20B1}";
$fmt = fn($n): string => number_format((float)$n, 2);

$products       = is_array($products ?? null) ? $products : [];
$lowStockAlerts = is_array($lowStockAlerts ?? null) ? $lowStockAlerts : [];
$isAdmin        = ($userRole ?? '') === ROLE_ADMIN;

$totalSkus       = count($products);
$lowStockCount   = count($lowStockAlerts);
$outOfStockCount = 0;
$totalInventoryValue = 0.00;

foreach ($products as $p) {
    $stock = (int)$p['current_stock'];
    $cost  = (float)$p['unit_cost'];
    if ($stock <= 0) {
        $outOfStockCount++;
    }
    $totalInventoryValue += ($stock * $cost);
}
?>

<div class="page-head">
    <div>
        <h1>Concessions Inventory &amp; Stock</h1>
        <p>Monitor snack bar stocks, receive real-time low-stock alerts, and record replenishment audits.</p>
    </div>
    <div class="inv-head-actions">
        <?php if ($isAdmin): ?>
            <a href="<?= BASE_URL; ?>/bundles" class="btn btn-ghost">
                Manage Combos &amp; Bundles &rarr;
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <p class="kpi-label">Active Concession SKUs</p>
        <p class="kpi-value"><?= $totalSkus; ?></p>
        <p class="kpi-note">A la carte snack &amp; drink items</p>
    </div>
    <div class="kpi <?= $lowStockCount > 0 ? 'is-key' : ''; ?>">
        <p class="kpi-label">Low Stock Alerts</p>
        <p class="kpi-value <?= $lowStockCount > 0 ? 'text-danger' : ''; ?>"><?= $lowStockCount; ?></p>
        <p class="kpi-note"><?= $lowStockCount > 0 ? 'Requires immediate restock' : 'All items at healthy stock'; ?></p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Out of Stock</p>
        <p class="kpi-value <?= $outOfStockCount > 0 ? 'text-danger' : ''; ?>"><?= $outOfStockCount; ?></p>
        <p class="kpi-note">Items unavailable at POS</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Inventory Asset Valuation</p>
        <p class="kpi-value"><?= $peso . $fmt($totalInventoryValue); ?></p>
        <p class="kpi-note">Based on unit wholesale cost</p>
    </div>
</div>

<?php if (!empty($lowStockAlerts)): ?>
<section class="card inv-alert-card" aria-label="Low Stock Alerts">
    <div class="inv-alert-header">
        <div class="inv-alert-title-wrap">
            <span class="inv-alert-icon" aria-hidden="true">&#9888;</span>
            <div>
                <h2 class="inv-alert-title">Critical Inventory Reorder Alert</h2>
                <p class="inv-alert-subtitle"><?= $lowStockCount; ?> concession <?= $lowStockCount === 1 ? 'item is' : 'items are'; ?> below the designated minimum safety threshold.</p>
            </div>
        </div>
        <span class="audit-badge is-danger">Threshold Breached</span>
    </div>

    <div class="inv-alert-grid">
        <?php foreach ($lowStockAlerts as $alert):
            $aId = (int)$alert['product_id'];
            $aStock = (int)$alert['current_stock'];
            $aReorder = (int)$alert['reorder_level'];
            $aDeficit = (int)$alert['units_deficit'];
        ?>
            <div class="inv-alert-item">
                <div class="inv-alert-item-info">
                    <strong><?= $e($alert['product_name']); ?></strong>
                    <span class="inv-alert-meta">
                        Category: <?= $e($alert['category']); ?> &bull; Current: <span class="text-danger"><strong><?= $aStock; ?></strong> units</span> (Min: <?= $aReorder; ?>)
                    </span>
                    <span class="inv-alert-deficit">Deficit: <?= $aDeficit; ?> <?= $aDeficit === 1 ? 'unit' : 'units'; ?> needed</span>
                </div>
                <?php if ($isAdmin): ?>
                    <button type="button"
                            class="btn btn-sm btn-ghost btn-open-adjust"
                            data-id="<?= $aId; ?>"
                            data-name="<?= $e($alert['product_name']); ?>"
                            data-stock="<?= $aStock; ?>">
                        Restock Item
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="card" aria-label="Product Stock Ledger">
    <div class="inv-toolbar">
        <div class="inv-search-wrap">
            <input type="search" id="invSearch" placeholder="Search product name or category..." aria-label="Filter inventory table">
        </div>
        <div class="inv-filter-group" role="group" aria-label="Stock status filter">
            <button type="button" class="inv-filter-btn active" data-filter="all">All Items</button>
            <button type="button" class="inv-filter-btn" data-filter="low">Low Stock Only</button>
            <button type="button" class="inv-filter-btn" data-filter="Popcorn">Popcorn</button>
            <button type="button" class="inv-filter-btn" data-filter="Beverage">Beverages</button>
            <button type="button" class="inv-filter-btn" data-filter="Snacks">Snacks</button>
            <button type="button" class="inv-filter-btn" data-filter="Candy">Candy</button>
        </div>
    </div>

    <div class="table-scroll">
        <table class="ledger inv-table" id="invTable">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Category</th>
                    <th class="num">Wholesale Cost</th>
                    <th class="num">Retail Price</th>
                    <th class="num">Margin</th>
                    <th class="num">Stock Level</th>
                    <th class="num">Reorder Level</th>
                    <th>Status</th>
                    <?php if ($isAdmin): ?>
                        <th style="text-align: right;">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? 9 : 8; ?>" class="empty-note">No concession products found in inventory.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p):
                        $pId = (int)$p['product_id'];
                        $stock = (int)$p['current_stock'];
                        $reorder = (int)$p['reorder_level'];
                        $cost = (float)$p['unit_cost'];
                        $price = (float)$p['retail_price'];
                        $isLow = (bool)($p['is_low_stock'] ?? ($stock <= $reorder));
                        $isOut = ($stock <= 0);
                        $marginPct = $price > 0 ? (($price - $cost) / $price) * 100 : 0;
                        $cat = (string)$p['category'];
                    ?>
                        <tr class="inv-row <?= $isOut ? 'is-out' : ($isLow ? 'is-low' : ''); ?>"
                            data-name="<?= $e(strtolower($p['product_name'] . ' ' . $cat)); ?>"
                            data-category="<?= $e($cat); ?>"
                            data-low="<?= ($isLow || $isOut) ? 'true' : 'false'; ?>">
                            <td>
                                <strong><?= $e($p['product_name']); ?></strong>
                                <small>SKU: SB-CON-<?= str_pad((string)$pId, 4, '0', STR_PAD_LEFT); ?></small>
                            </td>
                            <td>
                                <span class="pos-category-tag"><?= $e($cat); ?></span>
                            </td>
                            <td class="num"><?= $peso . $fmt($cost); ?></td>
                            <td class="num"><strong><?= $peso . $fmt($price); ?></strong></td>
                            <td class="num">
                                <span class="<?= $marginPct >= 50 ? 'is-positive' : ''; ?>">
                                    <?= number_format($marginPct, 1); ?>%
                                </span>
                            </td>
                            <td class="num">
                                <span class="stock-pill <?= $isOut ? 'pill-out' : ($isLow ? 'pill-low' : 'pill-ok'); ?>">
                                    <?= $stock; ?> units
                                </span>
                            </td>
                            <td class="num"><?= $reorder; ?></td>
                            <td>
                                <?php if ($isOut): ?>
                                    <span class="audit-badge is-danger">Out of Stock</span>
                                <?php elseif ($isLow): ?>
                                    <span class="audit-badge is-warn">Low Stock</span>
                                <?php else: ?>
                                    <span class="audit-badge is-info">Healthy</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td style="text-align: right;">
                                    <button type="button"
                                            class="btn btn-sm btn-ghost btn-open-adjust"
                                            data-id="<?= $pId; ?>"
                                            data-name="<?= $e($p['product_name']); ?>"
                                            data-stock="<?= $stock; ?>"
                                            title="Override or restock units">
                                        Adjust Stock
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($isAdmin): ?>
<dialog class="sched-dialog" id="adjustStockModal" aria-labelledby="adjustModalTitle">
    <form method="POST" action="<?= BASE_URL; ?>/inventory/adjust" id="adjustStockForm">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken); ?>">
        <input type="hidden" name="product_id" id="modalProductId" value="">

        <h2 id="adjustModalTitle">Adjust Stock Count</h2>
        <p class="sched-note">Manual adjustments are permanently recorded in the system audit trail.</p>

        <div class="sched-field">
            <label>Product Name</label>
            <input type="text" id="modalProductName" readonly disabled>
        </div>

        <div class="sched-row">
            <div class="sched-field">
                <label>Current Stock</label>
                <input type="text" id="modalCurrentStock" readonly disabled>
            </div>
            <div class="sched-field">
                <label for="newStockInput">New Unit Count *</label>
                <input type="number"
                       id="newStockInput"
                       name="new_stock"
                       min="0"
                       step="1"
                       required
                       placeholder="e.g. 100">
            </div>
        </div>

        <div class="sched-field">
            <label for="adjustReason">Adjustment Reason *</label>
            <select id="adjustReason" name="reason" required>
                <option value="Restock delivery shipment">Restock delivery shipment</option>
                <option value="Inventory audit physical recount">Inventory audit physical recount</option>
                <option value="Damaged / expired product write-off">Damaged / expired product write-off</option>
                <option value="Operational supply replenishment">Operational supply replenishment</option>
                <option value="Customer return / void correction">Customer return / void correction</option>
            </select>
        </div>

        <div class="sched-actions">
            <button type="button" class="btn btn-ghost" id="closeAdjustModalBtn">Cancel</button>
            <button type="submit" class="btn">Update Inventory</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script>
(function() {
    'use strict';

    // Search and filter
    var searchInput = document.getElementById('invSearch');
    var filterBtns  = document.querySelectorAll('.inv-filter-btn');
    var rows        = document.querySelectorAll('#invTable tbody .inv-row');
    var currentFilter = 'all';

    function filterTable() {
        var query = (searchInput ? searchInput.value : '').trim().toLowerCase();

        rows.forEach(function(row) {
            var name = row.getAttribute('data-name') || '';
            var cat  = row.getAttribute('data-category') || '';
            var isLow = row.getAttribute('data-low') === 'true';

            var matchSearch = !query || name.indexOf(query) !== -1;
            var matchCategory = true;

            if (currentFilter === 'low') {
                matchCategory = isLow;
            } else if (currentFilter !== 'all') {
                matchCategory = (cat === currentFilter);
            }

            row.style.display = (matchSearch && matchCategory) ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }

    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentFilter = btn.getAttribute('data-filter');
            filterTable();
        });
    });

    // Modal Handling
    var modal = document.getElementById('adjustStockModal');
    var openBtns = document.querySelectorAll('.btn-open-adjust');
    var closeBtn = document.getElementById('closeAdjustModalBtn');
    var idInput   = document.getElementById('modalProductId');
    var nameInput = document.getElementById('modalProductName');
    var curInput  = document.getElementById('modalCurrentStock');
    var newInput  = document.getElementById('newStockInput');

    openBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id    = btn.getAttribute('data-id');
            var name  = btn.getAttribute('data-name');
            var stock = btn.getAttribute('data-stock');

            if (idInput) idInput.value = id;
            if (nameInput) nameInput.value = name;
            if (curInput) curInput.value = stock + ' units';
            if (newInput) {
                newInput.value = stock;
                newInput.focus();
            }

            if (modal && typeof modal.showModal === 'function') {
                modal.showModal();
            }
        });
    });

    if (closeBtn && modal) {
        closeBtn.addEventListener('click', function() {
            modal.close();
        });
    }
})();
</script>
