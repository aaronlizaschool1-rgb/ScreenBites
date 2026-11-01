<?php
/**
 * ScreenBites - Checkout & Order Processing
 * Modeled after itim211-2b-shop/checkout.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seat_pricing.php';

$cart = $_SESSION['cart'] ?? [];

// Calculate totals
$grandTotal = 0.00;
$hasTickets = false;
$hasConcessions = false;

foreach ($cart as $item) {
    $grandTotal += ((float)$item['price'] * (int)$item['qty']);
    if ($item['type'] === 'ticket') {
        $hasTickets = true;
    } elseif ($item['type'] === 'concession' || $item['type'] === 'combo') {
        $hasConcessions = true;
    }
}

$subtotalVatEx = round($grandTotal / (1 + TAX_RATE), 2);
$vatAmount     = round($grandTotal - $subtotalVatEx, 2);

// Handle POST - Finalize Order
$orderCompleted = false;
$orderNumber = '';
$changeDue = 0.00;
$paymentMethod = 'Cash';
$amountPaid = 0.00;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($cart)) {
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $amountPaid    = (float)($_POST['amount_paid'] ?? $grandTotal);

    if ($amountPaid < $grandTotal) {
        $errorMsg = "Payment tender amount must be at least " . format_currency($grandTotal);
    } else {
        $changeDue = round($amountPaid - $grandTotal, 2);
        $orderNumber = 'SB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $userId = current_user()['user_id'] ?? 1;

        mysqli_begin_transaction($conn);
        try {
            // 1. Insert order
            $sqlOrder = "INSERT INTO orders (order_number, user_id, subtotal_amount, tax_amount, order_status, created_at)
                         VALUES (?, ?, ?, ?, 'Completed', NOW())";
            $stmtOrder = mysqli_prepare($conn, $sqlOrder);
            mysqli_stmt_bind_param($stmtOrder, "sidd", $orderNumber, $userId, $subtotalVatEx, $vatAmount);
            mysqli_stmt_execute($stmtOrder);
            $orderId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmtOrder);

            // 2. Insert tickets
            $sqlTicket = "INSERT INTO tickets (order_id, screening_id, seat_id, hall_id, price_charged, ticket_status)
                          VALUES (?, ?, ?, ?, ?, 'Valid')";
            $stmtTicket = mysqli_prepare($conn, $sqlTicket);

            // 3. Insert concessions
            $sqlOrderItem = "INSERT INTO order_items (order_id, product_id, quantity, unit_price_charged)
                             VALUES (?, ?, ?, ?)";
            $stmtOrderItem = mysqli_prepare($conn, $sqlOrderItem);

            // 3b. Insert combos
            $sqlOrderCombo = "INSERT INTO order_combos (order_id, combo_id, quantity, unit_price_charged)
                              VALUES (?, ?, ?, ?)";
            $stmtOrderCombo = mysqli_prepare($conn, $sqlOrderCombo);

            $sqlDecStock = "UPDATE products SET current_stock = current_stock - ? WHERE product_id = ?";
            $stmtDecStock = mysqli_prepare($conn, $sqlDecStock);

            foreach ($cart as $item) {
                if ($item['type'] === 'ticket') {
                    $scId = (int)$item['screening_id'];
                    $seatId = (int)$item['seat_id'];

                    // Fresh server-side anti-tamper recalculation of tier price
                    $stRes = mysqli_query($conn, "
                        SELECT s.hall_id, st.tier_name, st.tier_surcharge, sc.base_price
                        FROM seats s
                        INNER JOIN seat_tiers st ON s.tier_id = st.tier_id
                        INNER JOIN screenings sc ON sc.screening_id = {$scId}
                        WHERE s.seat_id = {$seatId} LIMIT 1
                    ");
                    $stRow = mysqli_fetch_assoc($stRes);
                    if (!$stRow) {
                        throw new Exception("Invalid seat or screening reference in cart.");
                    }

                    $hallId = (int)$stRow['hall_id'];
                    $price = calculate_seat_price((float)$stRow['base_price'], (float)$stRow['tier_surcharge']);

                    mysqli_stmt_bind_param($stmtTicket, "iiiid", $orderId, $scId, $seatId, $hallId, $price);
                    mysqli_stmt_execute($stmtTicket);
                } elseif ($item['type'] === 'concession') {
                    $prodId = (int)$item['id'];
                    $qty = (int)$item['qty'];
                    $price = (float)$item['price'];

                    // Check stock
                    $pStockRes = mysqli_query($conn, "SELECT current_stock, product_name FROM products WHERE product_id = {$prodId} LIMIT 1");
                    $pStock = mysqli_fetch_assoc($pStockRes);
                    if (!$pStock || (int)$pStock['current_stock'] < $qty) {
                        throw new Exception("Insufficient stock for " . ($pStock['product_name'] ?? 'product'));
                    }

                    mysqli_stmt_bind_param($stmtOrderItem, "iiid", $orderId, $prodId, $qty, $price);
                    mysqli_stmt_execute($stmtOrderItem);

                    mysqli_stmt_bind_param($stmtDecStock, "ii", $qty, $prodId);
                    mysqli_stmt_execute($stmtDecStock);
                } elseif ($item['type'] === 'combo') {
                    $comboId = (int)$item['id'];
                    $comboQty = (int)$item['qty'];
                    $price = (float)$item['price'];

                    // Insert into order_combos
                    mysqli_stmt_bind_param($stmtOrderCombo, "iiid", $orderId, $comboId, $comboQty, $price);
                    mysqli_stmt_execute($stmtOrderCombo);

                    // Deduct each constituent item in combo_items
                    $ciRes = mysqli_query($conn, "SELECT product_id, quantity FROM combo_items WHERE combo_id = {$comboId}");
                    while ($child = mysqli_fetch_assoc($ciRes)) {
                        $childId = (int)$child['product_id'];
                        $childNeeded = (int)$child['quantity'] * $comboQty;

                        $pStockRes = mysqli_query($conn, "SELECT current_stock, product_name FROM products WHERE product_id = {$childId} LIMIT 1");
                        $pStock = mysqli_fetch_assoc($pStockRes);
                        if (!$pStock || (int)$pStock['current_stock'] < $childNeeded) {
                            throw new Exception("Insufficient stock for constituent item " . ($pStock['product_name'] ?? 'product') . " in bundle.");
                        }

                        mysqli_stmt_bind_param($stmtDecStock, "ii", $childNeeded, $childId);
                        mysqli_stmt_execute($stmtDecStock);
                    }
                }
            }
            mysqli_stmt_close($stmtTicket);
            mysqli_stmt_close($stmtOrderItem);
            mysqli_stmt_close($stmtOrderCombo);
            mysqli_stmt_close($stmtDecStock);

            // 4. Record payment
            $sqlPay = "INSERT INTO payments (order_id, payment_method, amount_paid, change_returned, payment_date)
                       VALUES (?, ?, ?, ?, NOW())";
            $stmtPay = mysqli_prepare($conn, $sqlPay);
            mysqli_stmt_bind_param($stmtPay, "isdd", $orderId, $paymentMethod, $amountPaid, $changeDue);
            mysqli_stmt_execute($stmtPay);
            mysqli_stmt_close($stmtPay);

            mysqli_commit($conn);

            // Clear session cart
            $_SESSION['completed_order'] = [
                'order_number'   => $orderNumber,
                'items'          => $cart,
                'grand_total'    => $grandTotal,
                'vat_subtotal'   => $subtotalVatEx,
                'vat_amount'     => $vatAmount,
                'payment_method' => $paymentMethod,
                'amount_paid'    => $amountPaid,
                'change_due'     => $changeDue,
                'date'           => date('F j, Y, g:i A')
            ];
            unset($_SESSION['cart']);
            $orderCompleted = true;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $errorMsg = 'Transaction failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $orderCompleted ? 'Order Receipt' : 'Checkout';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($orderCompleted && isset($_SESSION['completed_order'])): 
    $ord = $_SESSION['completed_order'];
?>
    <div class="sb-card" style="max-width: 600px; margin: 0 auto; padding: 2rem;">
        <div style="text-align: center; border-bottom: 2px dashed var(--c-line, #dcdff0); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
            <h1 style="font-size: 1.6rem; color: var(--accent-gold, #d97706); margin-bottom: 0.25rem;">ScreenBites Cinema</h1>
            <p style="color: var(--c-muted, #5d6180); font-size: 0.9rem;">Official Digital Sales Receipt</p>
            <div style="margin-top: 0.5rem;">
                <span style="font-family: monospace; font-weight: 700; font-size: 1.1rem; color: var(--c-indigo, #2b2d7a); background: #eef2ff; border: 1px solid #c7d2fe; padding: 0.35rem 0.85rem; border-radius: 6px; display: inline-block;">
                    Order #<?= sanitize($ord['order_number']); ?>
                </span>
            </div>
            <p style="color: var(--c-muted, #5d6180); font-size: 0.85rem; margin-top: 0.5rem;"><?= sanitize($ord['date']); ?></p>
        </div>

        <table class="sb-table" style="margin-bottom: 1.5rem;">
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ord['items'] as $it): ?>
                    <tr>
                        <td>
                            <strong><?= sanitize($it['name']); ?></strong>
                            <?php if ($it['type'] === 'ticket' && !empty($it['tier_name'])): ?>
                                <span style="background: rgba(43, 45, 122, 0.1); color: var(--c-indigo, #2b2d7a); font-size: 0.72rem; padding: 0.12rem 0.4rem; border-radius: 4px; font-weight: 700; margin-left: 0.35rem; border: 1px solid rgba(43, 45, 122, 0.25);">
                                    🎟️ <?= sanitize($it['tier_name']); ?>
                                </span>
                            <?php endif; ?><br>
                            <small style="color: var(--c-muted, #5d6180);"><?= sanitize($it['details'] ?? ''); ?></small>
                        </td>
                        <td style="text-align: center;"><?= (int)$it['qty']; ?></td>
                        <td style="text-align: right;"><?= format_currency((float)$it['price'] * (int)$it['qty']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sb-dark-panel" style="padding: 1.15rem; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: #94a3b8; font-size: 0.9rem;">
                <span>Vatable Subtotal:</span>
                <span style="color: #f1f5f9; font-weight: 600;"><?= format_currency($ord['vat_subtotal']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: #94a3b8; font-size: 0.9rem;">
                <span>VAT (12%):</span>
                <span style="color: #f1f5f9; font-weight: 600;"><?= format_currency($ord['vat_amount']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; color: #ffffff; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 0.5rem;">
                <span>Total Paid:</span>
                <span style="color: var(--accent-gold, #f59e0b);"><?= format_currency($ord['grand_total']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.9rem; color: #94a3b8;">
                <span>Tender (<?= sanitize($ord['payment_method']); ?>):</span>
                <span style="color: #f1f5f9;"><?= format_currency($ord['amount_paid']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 1rem; font-weight: 600; color: #10b981;">
                <span>Change Returned:</span>
                <span><?= format_currency($ord['change_due']); ?></span>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: center;">
            <button type="button" class="btn btn-secondary" onclick="window.print()">Print Receipt</button>
            <a href="<?= BASE_URL; ?>/index.php" class="btn btn-primary">Done / Back to Home</a>
        </div>
    </div>

<?php elseif (empty($cart)): ?>
    <div class="sb-card" style="text-align: center; padding: 3rem 1rem;">
        <p style="font-size: 1.2rem; color: var(--text-muted); margin-bottom: 1.5rem;">Your cart is empty.</p>
        <a href="<?= BASE_URL; ?>/index.php" class="btn btn-primary">Return to Catalog</a>
    </div>

<?php else: ?>

    <div class="sb-page-header">
        <div>
            <h1>Order Checkout</h1>
            <p>Confirm transaction payment and issue order tickets.</p>
        </div>
        <a href="<?= BASE_URL; ?>/view_cart.php" class="btn btn-secondary">&larr; Back to Cart</a>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="sb-alert sb-alert-danger"><?= sanitize($errorMsg); ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start;" id="checkoutLayout">
        <div class="sb-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 style="font-size: 1.2rem; margin: 0;">Order Items Summary</h2>
                <span style="font-size: 0.8rem; color: var(--c-muted, #5d6180);">Concession quantities can be adjusted live</span>
            </div>

            <table class="sb-table" id="checkoutTable">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align: center; width: 140px;">Qty</th>
                        <th style="text-align: right; width: 120px;">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $key => $item): 
                        $price = (float)$item['price'];
                        $qty = (int)$item['qty'];
                        $lineTotal = $price * $qty;
                        $isTicket = ($item['type'] === 'ticket');
                        $maxQty = (int)($item['max_qty'] ?? 99);
                    ?>
                        <tr class="co-item-row" id="co-row-<?= sanitize($key); ?>" data-key="<?= sanitize($key); ?>" data-price="<?= $price; ?>">
                            <td>
                                <strong><?= sanitize($item['name']); ?></strong>
                                <?php if ($item['type'] === 'ticket' && !empty($item['tier_name'])): ?>
                                    <span style="background: rgba(43, 45, 122, 0.1); color: var(--c-indigo, #2b2d7a); font-size: 0.72rem; padding: 0.12rem 0.4rem; border-radius: 4px; font-weight: 700; margin-left: 0.35rem; border: 1px solid rgba(43, 45, 122, 0.25);">
                                        🎟️ <?= sanitize($item['tier_name']); ?>
                                    </span>
                                <?php elseif ($item['type'] === 'combo'): ?>
                                    <span style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-size: 0.72rem; padding: 0.12rem 0.4rem; border-radius: 4px; font-weight: 600; margin-left: 0.35rem; border: 1px solid rgba(245, 158, 11, 0.35);">
                                        🎁 Value Bundle
                                    </span>
                                <?php endif; ?><br>
                                <small style="color: var(--c-muted, #5d6180);"><?= sanitize($item['details'] ?? ''); ?></small>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($isTicket): ?>
                                    <span style="font-weight: 700; color: var(--c-indigo, #2b2d7a); background: #eef2ff; padding: 0.2rem 0.55rem; border-radius: 4px; font-size: 0.8rem; border: 1px solid #c7d2fe;">
                                        1 seat
                                    </span>
                                <?php else: ?>
                                    <div style="display: inline-flex; align-items: center; border: 1.5px solid var(--c-line, #dcdff0); border-radius: 6px; background: #fff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                        <button type="button" 
                                                class="btn-co-qty" 
                                                data-action="decrease" 
                                                data-key="<?= sanitize($key); ?>" 
                                                style="border: none; background: #f8fafc; color: var(--c-ink, #1d1f3a); width: 28px; height: 30px; font-weight: 700; cursor: pointer; display: grid; place-items: center; font-size: 1rem; border-right: 1px solid var(--c-line, #dcdff0);" 
                                                title="Decrease quantity">&minus;</button>
                                        <input type="number" 
                                               value="<?= $qty; ?>" 
                                               min="0" 
                                               max="<?= $maxQty; ?>" 
                                               data-key="<?= sanitize($key); ?>" 
                                               data-price="<?= $price; ?>" 
                                               class="co-qty-input" 
                                               style="width: 42px; height: 30px; text-align: center; border: none; font-size: 0.88rem; font-weight: 700; color: var(--c-ink, #1d1f3a); -moz-appearance: textfield;">
                                        <button type="button" 
                                                class="btn-co-qty" 
                                                data-action="increase" 
                                                data-key="<?= sanitize($key); ?>" 
                                                style="border: none; background: #f8fafc; color: var(--c-ink, #1d1f3a); width: 28px; height: 30px; font-weight: 700; cursor: pointer; display: grid; place-items: center; font-size: 1rem; border-left: 1px solid var(--c-line, #dcdff0);" 
                                                title="Increase quantity">&plus;</button>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--accent-gold, #d97706);" id="co-line-total-<?= sanitize($key); ?>">
                                <?= format_currency($lineTotal); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sb-card">
            <h2 style="font-size: 1.2rem; margin-bottom: 1rem;">Payment Details</h2>
            <form method="POST" action="<?= BASE_URL; ?>/checkout.php" id="checkoutPaymentForm">
                <div class="sb-dark-panel" style="padding: 1.15rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: #94a3b8; font-size: 0.85rem;">
                        <span>Vatable Subtotal:</span>
                        <span style="color: #f1f5f9; font-weight: 600;" id="coVatSubtotalDisplay"><?= format_currency($subtotalVatEx); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: #94a3b8; font-size: 0.85rem;">
                        <span>VAT (12%):</span>
                        <span style="color: #f1f5f9; font-weight: 600;" id="coVatAmountDisplay"><?= format_currency($vatAmount); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 800; color: #ffffff; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 0.6rem;">
                        <span>Grand Total:</span>
                        <span style="color: var(--accent-gold, #f59e0b);" id="coGrandTotalDisplay"><?= format_currency($grandTotal); ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-control" required>
                        <option value="Cash">Cash</option>
                        <option value="E-Wallet">E-Wallet (GCash / Maya)</option>
                        <option value="Card">Debit / Credit Card</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount_paid">Amount Tendered (PHP)</label>
                    <input type="number" 
                           step="0.01" 
                           min="<?= $grandTotal; ?>" 
                           name="amount_paid" 
                           id="amount_paid" 
                           value="<?= $grandTotal; ?>" 
                           class="form-control" 
                           required>
                    <small style="color: var(--c-muted, #5d6180); display: block; margin-top: 0.35rem;" id="tenderHelp">
                        Minimum payment: <?= format_currency($grandTotal); ?>
                    </small>
                </div>

                <button type="submit" class="btn btn-primary" id="btnConfirmOrder" style="width: 100%; margin-top: 0.5rem; font-weight: 700; padding: 0.75rem;">
                    Confirm &amp; Complete Order &rarr;
                </button>
            </form>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var updateUrl = <?= json_encode(BASE_URL . '/cart_update.php'); ?>;
    var taxRate = <?= json_encode(TAX_RATE); ?>;
    var currencySymbol = '₱';
    var isTenderManuallyModified = false;

    var tenderInput = document.getElementById('amount_paid');
    if (tenderInput) {
        tenderInput.addEventListener('input', function() {
            isTenderManuallyModified = true;
        });
    }

    function formatMoney(amount) {
        return currencySymbol + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalculateCheckoutDOM() {
        var rows = document.querySelectorAll('.co-item-row');
        if (rows.length === 0) {
            window.location.href = <?= json_encode(BASE_URL . '/view_cart.php'); ?>;
            return;
        }

        var grandTotal = 0.00;
        rows.forEach(function(row) {
            var price = parseFloat(row.getAttribute('data-price')) || 0.00;
            var input = row.querySelector('.co-qty-input');
            var qty = input ? (parseInt(input.value, 10) || 0) : 1;
            var lineTotal = price * qty;
            grandTotal += lineTotal;

            var subCell = row.querySelector('[id^="co-line-total-"]');
            if (subCell) {
                subCell.textContent = formatMoney(lineTotal);
            }
        });

        var vatSubtotal = grandTotal / (1 + taxRate);
        var vatAmount = grandTotal - vatSubtotal;

        var vSubElem = document.getElementById('coVatSubtotalDisplay');
        var vAmtElem = document.getElementById('coVatAmountDisplay');
        var gTotElem = document.getElementById('coGrandTotalDisplay');
        var tenderElem = document.getElementById('amount_paid');
        var tenderHelp = document.getElementById('tenderHelp');

        if (vSubElem) vSubElem.textContent = formatMoney(vatSubtotal);
        if (vAmtElem) vAmtElem.textContent = formatMoney(vatAmount);
        if (gTotElem) gTotElem.textContent = formatMoney(grandTotal);

        if (tenderElem) {
            tenderElem.setAttribute('min', grandTotal.toFixed(2));
            if (!isTenderManuallyModified || parseFloat(tenderElem.value) < grandTotal) {
                tenderElem.value = grandTotal.toFixed(2);
            }
        }
        if (tenderHelp) {
            tenderHelp.textContent = 'Minimum payment: ' + formatMoney(grandTotal);
        }
    }

    function syncCheckoutQty(key, newQty) {
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
                    var row = document.getElementById('co-row-' + key);
                    if (row) row.remove();
                    recalculateCheckoutDOM();
                } else if (data.item) {
                    var subCell = document.getElementById('co-line-total-' + key);
                    if (subCell && data.item.line_total_fmt) {
                        subCell.textContent = data.item.line_total_fmt;
                    }
                    if (data.grand_total_fmt) {
                        var gTot = document.getElementById('coGrandTotalDisplay');
                        if (gTot) gTot.textContent = data.grand_total_fmt;
                    }
                    if (data.vat_subtotal_fmt) {
                        var vSub = document.getElementById('coVatSubtotalDisplay');
                        if (vSub) vSub.textContent = data.vat_subtotal_fmt;
                    }
                    if (data.vat_amount_fmt) {
                        var vAmt = document.getElementById('coVatAmountDisplay');
                        if (vAmt) vAmt.textContent = data.vat_amount_fmt;
                    }
                    var tenderElem = document.getElementById('amount_paid');
                    if (tenderElem) {
                        tenderElem.setAttribute('min', data.grand_total.toFixed(2));
                        if (!isTenderManuallyModified || parseFloat(tenderElem.value) < data.grand_total) {
                            tenderElem.value = data.grand_total.toFixed(2);
                        }
                    }
                }
            }
        })
        .catch(function(err) {
            console.error('Checkout sync error:', err);
        });
    }

    document.querySelectorAll('.btn-co-qty').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var key = this.getAttribute('data-key');
            var action = this.getAttribute('data-action');
            var row = document.getElementById('co-row-' + key);
            if (!row) return;

            var input = row.querySelector('.co-qty-input');
            if (!input) return;

            var curVal = parseInt(input.value, 10) || 0;
            var maxVal = parseInt(input.getAttribute('max'), 10) || 999;
            var newVal = curVal;

            if (action === 'increase') {
                if (curVal < maxVal) newVal = curVal + 1;
            } else if (action === 'decrease') {
                newVal = curVal - 1;
            }

            if (newVal <= 0) {
                if (confirm('Remove this concession from order?')) {
                    row.remove();
                    recalculateCheckoutDOM();
                    syncCheckoutQty(key, 0);
                }
            } else {
                input.value = newVal;
                recalculateCheckoutDOM();
                syncCheckoutQty(key, newVal);
            }
        });
    });

    document.querySelectorAll('.co-qty-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var key = this.getAttribute('data-key');
            var val = parseInt(this.value, 10) || 0;
            var maxVal = parseInt(this.getAttribute('max'), 10) || 999;

            if (val <= 0) {
                var row = document.getElementById('co-row-' + key);
                if (confirm('Remove this concession from order?')) {
                    if (row) row.remove();
                    recalculateCheckoutDOM();
                    syncCheckoutQty(key, 0);
                } else {
                    this.value = 1;
                    recalculateCheckoutDOM();
                }
            } else {
                if (val > maxVal) {
                    val = maxVal;
                    this.value = val;
                }
                recalculateCheckoutDOM();
                syncCheckoutQty(key, val);
            }
        });
    });
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
