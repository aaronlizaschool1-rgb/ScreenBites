<?php
/**
 * ScreenBites - View Shopping Cart
 * Dynamic concessions quantity updating & synchronized checkout calculations
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/includes/header.php';

$cart = $_SESSION['cart'] ?? [];
$grandTotal = 0.00;
?>

<div class="sb-page-header">
    <div>
        <h1>Your Shopping Cart</h1>
        <p>Review your selected movie tickets and snack concessions before checkout.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= BASE_URL; ?>/index.php" class="btn btn-secondary">&larr; Movies</a>
        <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">&larr; Concessions</a>
    </div>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="sb-alert sb-alert-success"><?= sanitize($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="sb-alert sb-alert-danger"><?= sanitize($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<div id="cartContainer">
<?php if (empty($cart)): ?>
    <div class="sb-card" style="text-align: center; padding: 3rem 1rem;">
        <p style="font-size: 1.2rem; color: var(--c-muted, #5d6180); margin-bottom: 1.5rem;">Your cart is currently empty.</p>
        <a href="<?= BASE_URL; ?>/index.php" class="btn btn-primary">Browse Now Showing Movies</a>
        <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Order Snacks</a>
    </div>
<?php else: ?>
    <form method="POST" action="<?= BASE_URL; ?>/cart_update.php" id="cartForm">
        <input type="hidden" name="type" value="update" id="cartFormType">

        <div class="sb-card">
            <div class="sb-table-wrap">
                <table class="sb-table" id="cartTable">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th>Category / Screening Details</th>
                            <th style="text-align: right; width: 120px;">Unit Price</th>
                            <th style="text-align: center; width: 140px;">Quantity</th>
                            <th style="text-align: right; width: 130px;">Subtotal</th>
                            <th style="text-align: center; width: 80px;">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart as $key => $item):
                            $price = (float)$item['price'];
                            $qty = (int)$item['qty'];
                            $lineTotal = $price * $qty;
                            $grandTotal += $lineTotal;
                            $isTicket = ($item['type'] === 'ticket');
                            $maxQty = (int)($item['max_qty'] ?? 99);
                        ?>
                            <tr class="cart-item-row" id="row-<?= sanitize($key); ?>" data-key="<?= sanitize($key); ?>" data-price="<?= $price; ?>">
                                <td>
                                    <strong><?= sanitize($item['name']); ?></strong>
                                    <?php if ($item['type'] === 'ticket' && !empty($item['tier_name'])): ?>
                                        <span style="background: rgba(43, 45, 122, 0.1); color: var(--c-indigo, #2b2d7a); font-size: 0.72rem; padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 700; margin-left: 0.35rem; border: 1px solid rgba(43, 45, 122, 0.25);">
                                            🎟️ <?= sanitize($item['tier_name']); ?>
                                        </span>
                                        <?php if ((float)($item['tier_surcharge'] ?? 0) > 0): ?>
                                            <span style="background: rgba(245, 158, 11, 0.15); color: #b45309; font-size: 0.7rem; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 600; margin-left: 0.2rem;">
                                                +<?= format_currency($item['tier_surcharge']); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($item['type'] === 'combo'): ?>
                                        <span style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-size: 0.72rem; padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 600; margin-left: 0.35rem; border: 1px solid rgba(245, 158, 11, 0.35);">
                                            🎁 Value Bundle
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="color: var(--c-muted, #5d6180); font-size: 0.85rem;">
                                        <?= sanitize($item['details'] ?? 'General Item'); ?>
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight: 600;">
                                    <?= format_currency($price); ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($isTicket): ?>
                                        <span style="font-weight: 700; color: var(--c-indigo, #2b2d7a); background: #eef2ff; padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.82rem; border: 1px solid #c7d2fe;">
                                            1 seat
                                        </span>
                                        <input type="hidden" name="product_qty[<?= sanitize($key); ?>]" value="1">
                                    <?php else: ?>
                                        <div style="display: inline-flex; align-items: center; border: 1.5px solid var(--c-line, #dcdff0); border-radius: 6px; background: #fff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                            <button type="button" 
                                                    class="btn-qty-step" 
                                                    data-action="decrease" 
                                                    data-key="<?= sanitize($key); ?>" 
                                                    style="border: none; background: #f8fafc; color: var(--c-ink, #1d1f3a); width: 30px; height: 32px; font-weight: 700; cursor: pointer; display: grid; place-items: center; font-size: 1.1rem; border-right: 1px solid var(--c-line, #dcdff0);" 
                                                    title="Decrease quantity">&minus;</button>
                                            
                                            <input type="number" 
                                                   name="product_qty[<?= sanitize($key); ?>]" 
                                                   value="<?= $qty; ?>" 
                                                   min="0" 
                                                   max="<?= $maxQty; ?>" 
                                                   data-key="<?= sanitize($key); ?>"
                                                   data-price="<?= $price; ?>"
                                                   class="cart-qty-input" 
                                                   style="width: 46px; height: 32px; text-align: center; border: none; font-size: 0.92rem; font-weight: 700; color: var(--c-ink, #1d1f3a); -moz-appearance: textfield;">
                                            
                                            <button type="button" 
                                                    class="btn-qty-step" 
                                                    data-action="increase" 
                                                    data-key="<?= sanitize($key); ?>" 
                                                    style="border: none; background: #f8fafc; color: var(--c-ink, #1d1f3a); width: 30px; height: 32px; font-weight: 700; cursor: pointer; display: grid; place-items: center; font-size: 1.1rem; border-left: 1px solid var(--c-line, #dcdff0);" 
                                                    title="Increase quantity">&plus;</button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--accent-gold, #d97706); font-size: 0.95rem;" id="subtotal-<?= sanitize($key); ?>">
                                    <?= format_currency($lineTotal); ?>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" 
                                            class="btn-item-remove" 
                                            data-key="<?= sanitize($key); ?>" 
                                            style="background: none; border: none; color: #ef4444; font-size: 1.25rem; line-height: 1; cursor: pointer; padding: 0.35rem 0.5rem; border-radius: 4px; transition: all 0.15s ease;" 
                                            title="Remove item">
                                        &times;
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $subtotalVatEx = round($grandTotal / (1 + TAX_RATE), 2);
            $vatAmount     = round($grandTotal - $subtotalVatEx, 2);
            ?>

            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div style="color: var(--c-muted, #5d6180); font-size: 0.85rem;">
                    💡 Quantities automatically recalculate totals in real time and synchronize with checkout.
                </div>

                <div class="sb-dark-panel" style="min-width: 280px; padding: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.9rem;">
                        <span>Vatable Subtotal:</span>
                        <span style="color: #f1f5f9; font-weight: 600;" id="cartVatSubtotalDisplay"><?= format_currency($subtotalVatEx); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.9rem;">
                        <span>VAT (12% Included):</span>
                        <span style="color: #f1f5f9; font-weight: 600;" id="cartVatAmountDisplay"><?= format_currency($vatAmount); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 800; color: #ffffff; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 0.75rem;">
                        <span>Grand Total:</span>
                        <span style="color: var(--accent-gold, #f59e0b);" id="cartGrandTotalDisplay"><?= format_currency($grandTotal); ?></span>
                    </div>

                    <a href="<?= BASE_URL; ?>/checkout.php" id="btnProceedCheckout" class="btn btn-primary" style="width: 100%; margin-top: 1rem; text-align: center; display: block; font-weight: 700;">
                        Proceed to Checkout &rarr;
                    </a>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>
</div>

<!-- DYNAMIC REAL-TIME CART CONTROLLER (Vanilla JS) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var updateUrl = <?= json_encode(BASE_URL . '/cart_update.php'); ?>;
    var taxRate = <?= json_encode(TAX_RATE); ?>;
    var currencySymbol = '₱';

    function formatMoney(amount) {
        return currencySymbol + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalculateCartDOM() {
        var rows = document.querySelectorAll('.cart-item-row');
        if (rows.length === 0) {
            var container = document.getElementById('cartContainer');
            if (container) {
                container.innerHTML = '<div class="sb-card" style="text-align: center; padding: 3rem 1rem;">' +
                    '<p style="font-size: 1.2rem; color: var(--c-muted, #5d6180); margin-bottom: 1.5rem;">Your cart is currently empty.</p>' +
                    '<a href="<?= BASE_URL; ?>/index.php" class="btn btn-primary">Browse Now Showing Movies</a>' +
                    '<a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Order Snacks</a>' +
                    '</div>';
            }
            return;
        }

        var grandTotal = 0.00;
        rows.forEach(function(row) {
            var price = parseFloat(row.getAttribute('data-price')) || 0.00;
            var input = row.querySelector('.cart-qty-input');
            var qty = input ? (parseInt(input.value, 10) || 0) : 1;
            var lineTotal = price * qty;
            grandTotal += lineTotal;

            var subCell = row.querySelector('[id^="subtotal-"]');
            if (subCell) {
                subCell.textContent = formatMoney(lineTotal);
            }
        });

        var vatSubtotal = grandTotal / (1 + taxRate);
        var vatAmount = grandTotal - vatSubtotal;

        var vSubElem = document.getElementById('cartVatSubtotalDisplay');
        var vAmtElem = document.getElementById('cartVatAmountDisplay');
        var gTotElem = document.getElementById('cartGrandTotalDisplay');

        if (vSubElem) vSubElem.textContent = formatMoney(vatSubtotal);
        if (vAmtElem) vAmtElem.textContent = formatMoney(vatAmount);
        if (gTotElem) gTotElem.textContent = formatMoney(grandTotal);
    }

    function syncQtyWithServer(key, newQty) {
        var formData = new FormData();
        formData.append('type', 'ajax_update_qty');
        formData.append('item_key', key);
        formData.append('quantity', newQty);

        fetch(updateUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                if (data.removed) {
                    var row = document.getElementById('row-' + key);
                    if (row) row.remove();
                    recalculateCartDOM();
                } else if (data.item) {
                    var subCell = document.getElementById('subtotal-' + key);
                    if (subCell && data.item.line_total_fmt) {
                        subCell.textContent = data.item.line_total_fmt;
                    }
                    if (data.grand_total_fmt) {
                        var gTot = document.getElementById('cartGrandTotalDisplay');
                        if (gTot) gTot.textContent = data.grand_total_fmt;
                    }
                    if (data.vat_subtotal_fmt) {
                        var vSub = document.getElementById('cartVatSubtotalDisplay');
                        if (vSub) vSub.textContent = data.vat_subtotal_fmt;
                    }
                    if (data.vat_amount_fmt) {
                        var vAmt = document.getElementById('cartVatAmountDisplay');
                        if (vAmt) vAmt.textContent = data.vat_amount_fmt;
                    }
                }
            }
        })
        .catch(function(err) {
            console.error('Cart sync error:', err);
        });
    }

    function removeItemServer(key) {
        var formData = new FormData();
        formData.append('type', 'ajax_remove_item');
        formData.append('item_key', key);

        fetch(updateUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                var row = document.getElementById('row-' + key);
                if (row) row.remove();
                recalculateCartDOM();
            }
        })
        .catch(function(err) {
            console.error('Remove error:', err);
        });
    }

    // Handle +/- stepper buttons
    document.querySelectorAll('.btn-qty-step').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var key = this.getAttribute('data-key');
            var action = this.getAttribute('data-action');
            var row = document.getElementById('row-' + key);
            if (!row) return;

            var input = row.querySelector('.cart-qty-input');
            if (!input) return;

            var curVal = parseInt(input.value, 10) || 0;
            var maxVal = parseInt(input.getAttribute('max'), 10) || 999;
            var newVal = curVal;

            if (action === 'increase') {
                if (curVal < maxVal) {
                    newVal = curVal + 1;
                }
            } else if (action === 'decrease') {
                newVal = curVal - 1;
            }

            if (newVal <= 0) {
                if (confirm('Remove this item from your cart?')) {
                    row.remove();
                    recalculateCartDOM();
                    syncQtyWithServer(key, 0);
                }
            } else {
                input.value = newVal;
                recalculateCartDOM();
                syncQtyWithServer(key, newVal);
            }
        });
    });

    // Handle direct input editing
    document.querySelectorAll('.cart-qty-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var key = this.getAttribute('data-key');
            var val = parseInt(this.value, 10) || 0;
            var maxVal = parseInt(this.getAttribute('max'), 10) || 999;

            if (val <= 0) {
                var row = document.getElementById('row-' + key);
                if (confirm('Remove this item from your cart?')) {
                    if (row) row.remove();
                    recalculateCartDOM();
                    syncQtyWithServer(key, 0);
                } else {
                    this.value = 1;
                    recalculateCartDOM();
                }
            } else {
                if (val > maxVal) {
                    val = maxVal;
                    this.value = val;
                }
                recalculateCartDOM();
                syncQtyWithServer(key, val);
            }
        });
    });

    // Handle remove buttons
    document.querySelectorAll('.btn-item-remove').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var key = this.getAttribute('data-key');
            if (confirm('Remove this item from your cart?')) {
                var row = document.getElementById('row-' + key);
                if (row) row.remove();
                recalculateCartDOM();
                removeItemServer(key);
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
