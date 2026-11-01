<?php
/**
 * ScreenBites - View Shopping Cart
 * Dynamic concessions quantity updating & synchronized checkout calculations
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seat_holds.php';

// Sync cart with active holds (auto-remove expired seats)
$sessionToken = session_id();
$removedHolds = sync_cart_with_seat_holds($conn, $sessionToken);
if ($removedHolds > 0) {
    $_SESSION['flash_error'] = "Your 8-minute seat reservation expired. The seat(s) have been released back to other customers.";
}

// Check for hold countdown
$holdExpiryTimestamp = get_session_hold_expiry($conn, $sessionToken);

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

<?php if ($holdExpiryTimestamp && $holdExpiryTimestamp > time()): ?>
    <div class="seat-hold-banner" id="seatHoldBanner" data-expiry="<?= $holdExpiryTimestamp; ?>">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.5rem;">⏱️</span>
            <div>
                <strong style="color: #f59e0b; font-size: 1rem; display: block;">Seats Temporarily Held</strong>
                <span style="font-size: 0.85rem; color: var(--text-muted);">Please complete checkout before the timer expires to secure your seats.</span>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Time Remaining:</span>
            <span class="seat-hold-timer" id="holdCountdownDisplay">--:--</span>
        </div>
    </div>
<?php endif; ?>

<style>
/* Robust Cart Table Display & Alignment */
#cartTable {
    width: 100% !important;
    border-collapse: collapse !important;
    table-layout: fixed !important;
    min-width: 820px;
}
#cartTable thead tr {
    display: table-row !important;
}
#cartTable tbody tr,
#cartTable tr.cart-item-row {
    display: table-row !important;
    border-bottom: 1px solid var(--c-line, #dcdff0) !important;
    padding: 0 !important;
    gap: 0 !important;
    background: transparent;
}
#cartTable th,
#cartTable td {
    display: table-cell !important;
    vertical-align: middle !important;
    box-sizing: border-box !important;
}
#cartTable tbody tr:hover {
    background-color: rgba(43, 45, 122, 0.015);
}
</style>

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

        <div class="sb-card" style="padding: 0; overflow: hidden; border: 1.5px solid var(--c-line, #dcdff0); box-shadow: 0 4px 14px rgba(0,0,0,0.03); border-radius: 10px;">
            <div class="sb-table-wrap">
                <table class="sb-table" id="cartTable">
                    <colgroup>
                        <col style="width: 32%;">
                        <col style="width: 28%;">
                        <col style="width: 13%;">
                        <col style="width: 13%;">
                        <col style="width: 14%;">
                    </colgroup>
                    <thead>
                        <tr style="background: rgba(43, 45, 122, 0.04); border-bottom: 2px solid var(--c-line, #dcdff0);">
                            <th style="width: 32%; text-align: left; padding: 1.1rem 1.25rem; vertical-align: middle;">Item Description</th>
                            <th style="width: 28%; text-align: left; padding: 1.1rem 1.25rem; vertical-align: middle;">Screening / Concession Details</th>
                            <th style="width: 13%; text-align: right; padding: 1.1rem 1.25rem; vertical-align: middle;">Unit Price</th>
                            <th style="width: 13%; text-align: center; padding: 1.1rem 1.25rem; vertical-align: middle;">Quantity</th>
                            <th style="width: 14%; text-align: right; padding: 1.1rem 1.25rem; vertical-align: middle;">Subtotal</th>
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
                            <tr class="cart-item-row" id="row-<?= sanitize($key); ?>" data-key="<?= sanitize($key); ?>" data-price="<?= $price; ?>" style="border-bottom: 1px solid var(--c-line, #dcdff0);">
                                <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                    <div style="font-size: 1rem; font-weight: 700; color: var(--c-ink, #1d1f3a); line-height: 1.35; margin-bottom: 0.25rem;">
                                        <?= sanitize($item['name']); ?>
                                    </div>
                                    <div style="display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap;">
                                        <?php if ($item['type'] === 'ticket' && !empty($item['tier_name'])): ?>
                                            <span style="background: rgba(43, 45, 122, 0.08); color: var(--c-indigo, #2b2d7a); font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 700; border: 1px solid rgba(43, 45, 122, 0.2);">
                                                🎟️ <?= sanitize($item['tier_name']); ?>
                                            </span>
                                            <?php if ((float)($item['tier_surcharge'] ?? 0) > 0): ?>
                                                <span style="background: rgba(245, 158, 11, 0.15); color: #b45309; font-size: 0.7rem; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 600;">
                                                    +<?= format_currency($item['tier_surcharge']); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($item['type'] === 'combo'): ?>
                                            <span style="background: rgba(245, 158, 11, 0.15); color: #b45309; font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 700; border: 1px solid rgba(245, 158, 11, 0.3);">
                                                🎁 Value Bundle
                                            </span>
                                        <?php endif; ?>

                                        <a href="<?= BASE_URL; ?>/cart_update.php?type=remove&code=<?= urlencode($key); ?>" 
                                           onclick="return confirm('Remove this item from your cart?');" 
                                           style="background: none; border: none; color: #ef4444; font-size: 0.75rem; text-decoration: underline; cursor: pointer; padding: 0.1rem 0.3rem;"
                                           title="Remove item">
                                            Remove
                                        </a>
                                    </div>
                                </td>
                                <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                    <span style="color: var(--c-muted, #5d6180); font-size: 0.88rem; line-height: 1.45; display: block;">
                                        <?= sanitize($item['details'] ?? 'General Item'); ?>
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight: 600; font-family: monospace; font-size: 0.95rem; padding: 1rem 1.25rem; vertical-align: middle;">
                                    <?= format_currency($price); ?>
                                </td>
                                <td style="text-align: center; padding: 1rem 1.25rem; vertical-align: middle;">
                                    <?php if ($isTicket): ?>
                                        <span style="font-weight: 700; color: var(--c-indigo, #2b2d7a); background: #eef2ff; padding: 0.3rem 0.75rem; border-radius: 6px; font-size: 0.82rem; border: 1px solid #c7d2fe; display: inline-block;">
                                            1 seat
                                        </span>
                                        <input type="hidden" name="product_qty[<?= sanitize($key); ?>]" value="1">
                                    <?php else: ?>
                                        <div style="display: inline-flex; align-items: center; border: 1.5px solid var(--c-line, #dcdff0); border-radius: 6px; background: #fff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); vertical-align: middle;">
                                            <button type="button" 
                                                    onclick="stepQty('<?= sanitize($key); ?>', -1)" 
                                                    style="border: none; background: #f8fafc; color: var(--c-ink, #1d1f3a); width: 32px; height: 34px; font-weight: 700; cursor: pointer; display: grid; place-items: center; font-size: 1.1rem; border-right: 1px solid var(--c-line, #dcdff0);" 
                                                    title="Decrease quantity">&minus;</button>
                                            
                                            <input type="number" 
                                                   id="qty-<?= sanitize($key); ?>"
                                                   name="product_qty[<?= sanitize($key); ?>]" 
                                                   value="<?= $qty; ?>" 
                                                   min="1" 
                                                   max="<?= $maxQty; ?>" 
                                                   onchange="document.getElementById('cartForm').submit();"
                                                   style="width: 48px; height: 34px; text-align: center; border: none; font-size: 0.95rem; font-weight: 700; color: var(--c-ink, #1d1f3a); -moz-appearance: textfield;">
                                            
                                            <button type="button" 
                                                    onclick="stepQty('<?= sanitize($key); ?>', 1)" 
                                                    style="border: none; background: #f8fafc; color: var(--c-ink, #1d1f3a); width: 32px; height: 34px; font-weight: 700; cursor: pointer; display: grid; place-items: center; font-size: 1.1rem; border-left: 1px solid var(--c-line, #dcdff0);" 
                                                    title="Increase quantity">&plus;</button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--accent-gold, #d97706); font-size: 1rem; padding: 1rem 1.25rem; vertical-align: middle;" id="subtotal-<?= sanitize($key); ?>">
                                    <?= format_currency($lineTotal); ?>
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

            
                <div class="sb-card" style="min-width: 320px; padding: 1.5rem; background: #ffffff; border: 1.5px solid var(--c-line, #dcdff0); box-shadow: 0 4px 12px rgba(0,0,0,0.04); border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.6rem; color: var(--c-muted, #5d6180); font-size: 0.92rem;">
                        <span>Vatable Subtotal:</span>
                        <strong style="color: var(--c-ink, #1d1f3a); font-family: monospace;" id="cartVatSubtotalDisplay"><?= format_currency($subtotalVatEx); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: var(--c-muted, #5d6180); font-size: 0.92rem;">
                        <span>VAT (12% Included):</span>
                        <strong style="color: var(--c-ink, #1d1f3a); font-family: monospace;" id="cartVatAmountDisplay"><?= format_currency($vatAmount); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; font-size: 1.25rem; font-weight: 800; color: var(--c-ink, #1d1f3a); border-top: 2px solid var(--c-line, #dcdff0); padding-top: 0.85rem;">
                        <span>Grand Total:</span>
                        <strong style="color: var(--accent-gold, #d97706); font-size: 1.55rem; font-family: 'Bricolage Grotesque', 'Figtree', sans-serif;" id="cartGrandTotalDisplay"><?= format_currency($grandTotal); ?></strong>
                    </div>

                    <a href="<?= BASE_URL; ?>/checkout.php" id="btnProceedCheckout" class="btn btn-primary" style="width: 100%; margin-top: 1.25rem; text-align: center; display: block; font-weight: 700; padding: 0.75rem 1rem; font-size: 1rem; box-shadow: 0 2px 6px rgba(43, 45, 122, 0.25);">
                        Proceed to Checkout &rarr;
                    </a>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>
</div>

<!-- PROCEDURAL CART CONTROLLER (Zero JSON - Native Form Submissions) -->
<script>
function stepQty(key, delta) {
    var input = document.getElementById('qty-' + key);
    if (!input) return;
    var cur = parseInt(input.value, 10) || 0;
    var max = parseInt(input.getAttribute('max'), 10) || 999;
    var next = cur + delta;
    if (next <= 0) {
        if (confirm('Remove this item from your cart?')) {
            window.location.href = '<?= BASE_URL; ?>/cart_update.php?type=remove&code=' + encodeURIComponent(key);
        }
    } else {
        input.value = Math.min(next, max);
        document.getElementById('cartForm').submit();
    }
}







// Live Seat Hold Countdown Timer (FR4)
document.addEventListener('DOMContentLoaded', function() {
    var banner = document.getElementById('seatHoldBanner');
    if (banner) {
        var expiryTimestamp = parseInt(banner.getAttribute('data-expiry'), 10);
        var display = document.getElementById('holdCountdownDisplay');

        function updateCountdown() {
            var now = Math.floor(Date.now() / 1000);
            var remaining = expiryTimestamp - now;

            if (remaining <= 0) {
                display.textContent = '00:00';
                display.style.color = '#ef4444';
                // Reload page to trigger PHP hold expiration and cart cleanup
                window.location.reload();
                return;
            }

            var mins = Math.floor(remaining / 60);
            var secs = remaining % 60;
            display.textContent = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;

            if (remaining <= 60) {
                display.style.color = '#ef4444';
            }
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
