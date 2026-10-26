<?php

use Core\Auth;

$e = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$peso = "\u{20B1}";
$fmt = fn($n): string => number_format((float)$n, 2);

$combos   = is_array($combos ?? null) ? $combos : [];
$products = is_array($products ?? null) ? $products : [];
$isAdmin  = ($userRole ?? '') === ROLE_ADMIN;

$totalCombos = count($combos);
$totalSavingsSum = 0;
$maxSavings = 0;

foreach ($combos as $c) {
    $cPrice = (float)$c['combo_price'];
    $iPrice = (float)($c['individual_sum_price'] ?? 0);
    $savings = max(0, $iPrice - $cPrice);
    $totalSavingsSum += $savings;
    if ($savings > $maxSavings) {
        $maxSavings = $savings;
    }
}
$avgSavings = $totalCombos > 0 ? ($totalSavingsSum / $totalCombos) : 0;
?>

<div class="page-head">
    <div>
        <h1>Promotional Combos &amp; Bundles</h1>
        <p>Package concession items into discounted promotional bundles to drive snack bar sales (FR6).</p>
    </div>
    <div class="bundle-head-actions">
        <?php if ($isAdmin): ?>
            <button type="button" class="btn" id="openCreateBundleBtn">
                + Create New Bundle
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <p class="kpi-label">Active Combos</p>
        <p class="kpi-value"><?= $totalCombos; ?></p>
        <p class="kpi-note">Available for quick-add at POS</p>
    </div>
    <div class="kpi is-key">
        <p class="kpi-label">Average Customer Savings</p>
        <p class="kpi-value is-positive"><?= $peso . $fmt($avgSavings); ?></p>
        <p class="kpi-note">Across active packages</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Maximum Bundle Discount</p>
        <p class="kpi-value is-positive"><?= $peso . $fmt($maxSavings); ?></p>
        <p class="kpi-note">Highest single-deal discount</p>
    </div>
</div>

<div class="bundle-grid" id="bundleGrid">
    <?php if (empty($combos)): ?>
        <div class="card" style="grid-column: 1 / -1;">
            <p class="empty-note">No promotional bundles created yet. Click "+ Create New Bundle" above to construct one.</p>
        </div>
    <?php else: ?>
        <?php foreach ($combos as $combo):
            $cId = (int)$combo['combo_id'];
            $cPrice = (float)$combo['combo_price'];
            $indPrice = (float)($combo['individual_sum_price'] ?? 0);
            $savings = max(0, $indPrice - $cPrice);
            $savingsPct = $indPrice > 0 ? ($savings / $indPrice) * 100 : 0;
        ?>
            <article class="bundle-card card">
                <div class="bundle-card-header">
                    <div>
                        <span class="audit-badge is-info">Promotional Deal</span>
                        <h2 class="bundle-title"><?= $e($combo['combo_name']); ?></h2>
                    </div>
                    <div class="bundle-price-box">
                        <span class="bundle-price"><?= $peso . $fmt($cPrice); ?></span>
                        <?php if ($indPrice > $cPrice): ?>
                            <del class="bundle-old-price"><?= $peso . $fmt($indPrice); ?></del>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($combo['description'])): ?>
                    <p class="bundle-desc"><?= $e($combo['description']); ?></p>
                <?php endif; ?>

                <div class="bundle-items-box">
                    <span class="bundle-items-heading">Included Concessions:</span>
                    <p class="bundle-breakdown-text"><?= $e($combo['item_breakdown'] ?? 'Custom products bundle'); ?></p>
                </div>

                <div class="bundle-card-footer">
                    <?php if ($savings > 0): ?>
                        <span class="bundle-badge-save">
                            Save <?= $peso . $fmt($savings); ?> (<?= number_format($savingsPct, 1); ?>% OFF)
                        </span>
                    <?php else: ?>
                        <span class="audit-badge">Standard Price</span>
                    <?php endif; ?>
                    <span class="audit-badge is-info">Active</span>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<dialog class="sched-dialog bundle-dialog" id="createBundleModal" aria-labelledby="bundleModalTitle">
    <form method="POST" action="<?= BASE_URL; ?>/bundles/create" id="createBundleForm">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken); ?>">

        <h2 id="bundleModalTitle">Create Promotional Bundle</h2>
        <p class="sched-note">Bundle multiple snack and beverage items at a custom package price (FR6).</p>

        <div class="sched-field">
            <label for="comboNameInput">Bundle Package Name *</label>
            <input type="text"
                   id="comboNameInput"
                   name="combo_name"
                   required
                   placeholder="e.g. Cinema Date Night Feast">
        </div>

        <div class="sched-field">
            <label for="comboDescInput">Marketing Description</label>
            <input type="text"
                   id="comboDescInput"
                   name="description"
                   placeholder="e.g. 2 Large Butter Popcorn + 2 Sodas + Nachos">
        </div>

        <div class="sched-field">
            <label for="comboPriceInput">Promotional Bundle Price (<?= $peso; ?>) *</label>
            <input type="number"
                   id="comboPriceInput"
                   name="combo_price"
                   step="0.01"
                   min="0.01"
                   required
                   placeholder="e.g. 350.00">
        </div>

        <div class="bundle-inclusions-wrap">
            <div class="bundle-inclusions-head">
                <label>Included Concession Items *</label>
                <button type="button" class="btn btn-sm btn-ghost" id="addItemRowBtn">
                    + Add Concession Item
                </button>
            </div>

            <div class="bundle-rows-container" id="bundleRowsContainer">

                <div class="bundle-item-row">
                    <div class="sched-field flex-2">
                        <select name="product_ids[]" class="bundle-prod-select" required>
                            <option value="">-- Choose Concession Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['product_id']; ?>" data-price="<?= (float)$p['retail_price']; ?>">
                                    <?= $e($p['product_name']); ?> (<?= $e($p['category']); ?> &bull; <?= $peso . $fmt($p['retail_price']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sched-field flex-1">
                        <input type="number" name="quantities[]" class="bundle-prod-qty" min="1" value="1" required placeholder="Qty">
                    </div>
                    <button type="button" class="btn-remove-row" title="Remove item row">&times;</button>
                </div>
            </div>

            <div class="bundle-preview-card">
                <div class="preview-row">
                    <span>Individual Sum Value:</span>
                    <strong id="previewIndSum"><?= $peso; ?>0.00</strong>
                </div>
                <div class="preview-row">
                    <span>Proposed Bundle Price:</span>
                    <strong id="previewBundlePrice"><?= $peso; ?>0.00</strong>
                </div>
                <div class="preview-row" id="previewSavingsRow">
                    <span>Customer Discount:</span>
                    <strong class="is-positive" id="previewSavings"><?= $peso; ?>0.00 (0%)</strong>
                </div>
            </div>
        </div>

        <div class="sched-actions">
            <button type="button" class="btn btn-ghost" id="closeBundleModalBtn">Cancel</button>
            <button type="submit" class="btn">Create Package</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script>
(function() {
    'use strict';

    var modal       = document.getElementById('createBundleModal');
    var openBtn     = document.getElementById('openCreateBundleBtn');
    var closeBtn    = document.getElementById('closeBundleModalBtn');
    var addRowBtn   = document.getElementById('addItemRowBtn');
    var container   = document.getElementById('bundleRowsContainer');
    var priceInput  = document.getElementById('comboPriceInput');

    var indSumEl     = document.getElementById('previewIndSum');
    var bundlePriceEl= document.getElementById('previewBundlePrice');
    var savingsEl    = document.getElementById('previewSavings');

    var peso = <?= json_encode($peso); ?>;

    function recalculatePreview() {
        if (!container) return;
        var totalInd = 0;
        var rows = container.querySelectorAll('.bundle-item-row');

        rows.forEach(function(row) {
            var select = row.querySelector('.bundle-prod-select');
            var qtyInput = row.querySelector('.bundle-prod-qty');
            if (!select || !qtyInput) return;

            var selectedOpt = select.options[select.selectedIndex];
            var unitPrice = selectedOpt ? (parseFloat(selectedOpt.getAttribute('data-price')) || 0) : 0;
            var qty = parseInt(qtyInput.value, 10) || 1;

            totalInd += (unitPrice * qty);
        });

        var bundlePrice = parseFloat(priceInput ? priceInput.value : 0) || 0;
        var savings = Math.max(0, totalInd - bundlePrice);
        var savingsPct = totalInd > 0 ? ((savings / totalInd) * 100) : 0;

        if (indSumEl) indSumEl.textContent = peso + totalInd.toFixed(2);
        if (bundlePriceEl) bundlePriceEl.textContent = peso + bundlePrice.toFixed(2);
        if (savingsEl) {
            savingsEl.textContent = peso + savings.toFixed(2) + ' (' + savingsPct.toFixed(1) + '% OFF)';
        }
    }

    if (openBtn && modal) {
        openBtn.addEventListener('click', function() {
            recalculatePreview();
            if (typeof modal.showModal === 'function') {
                modal.showModal();
            }
        });
    }

    if (closeBtn && modal) {
        closeBtn.addEventListener('click', function() {
            modal.close();
        });
    }

    if (addRowBtn && container) {
        addRowBtn.addEventListener('click', function() {
            var firstRow = container.querySelector('.bundle-item-row');
            if (!firstRow) return;

            var clone = firstRow.cloneNode(true);
            var select = clone.querySelector('.bundle-prod-select');
            var qtyInput = clone.querySelector('.bundle-prod-qty');

            if (select) select.selectedIndex = 0;
            if (qtyInput) qtyInput.value = 1;

            container.appendChild(clone);
            recalculatePreview();
        });
    }

    if (container) {
        container.addEventListener('change', recalculatePreview);
        container.addEventListener('input', recalculatePreview);
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-remove-row');
            if (!btn) return;
            var rows = container.querySelectorAll('.bundle-item-row');
            if (rows.length > 1) {
                btn.closest('.bundle-item-row').remove();
                recalculatePreview();
            } else {
                alert('At least one concession item must remain in the bundle.');
            }
        });
    }

    if (priceInput) {
        priceInput.addEventListener('input', recalculatePreview);
    }
})();
</script>
