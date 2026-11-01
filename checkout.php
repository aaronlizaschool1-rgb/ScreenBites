<?php
/**
 * ScreenBites - Checkout & Order Processing
 * Modeled after itim211-2b-shop/checkout.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seat_pricing.php';
require_once __DIR__ . '/includes/seat_holds.php';
require_once __DIR__ . '/includes/mailer.php';

// Sync cart with active holds (FR4: Seat Hold & Timeout auto-expiration)
$sessionToken = session_id();
$removedHolds = sync_cart_with_seat_holds($conn, $sessionToken);
if ($removedHolds > 0) {
    set_flash('error', "Your 8-minute seat reservation expired. The seat(s) have been released back to the hall.");
    header("Location: " . BASE_URL . "/view_cart.php");
    exit;
}

$holdExpiryTimestamp = get_session_hold_expiry($conn, $sessionToken);

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
    $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
    $amountPaidRaw = trim($_POST['amount_paid'] ?? '');
    $amountPaid    = (float)$amountPaidRaw;
    $customerName  = trim($_POST['customer_name'] ?? '');
    if (empty($customerName)) {
        $customerName = is_logged_in() ? (current_user()['full_name'] ?? 'Staff Order') : 'Valued Guest';
    }
    $customerEmail = trim($_POST['customer_email'] ?? '');

    // Pure server-side validation (Anti-HTML5 Validation Constraint)
    if (empty($paymentMethod)) {
        $errorMsg = "Please select a payment method.";
    } elseif ($amountPaidRaw === '' || !is_numeric($amountPaidRaw) || $amountPaid < $grandTotal) {
        $errorMsg = "Payment tender amount must be at least " . format_currency($grandTotal);
    } elseif (!empty($customerEmail) && !validate_email($customerEmail)) {
        $errorMsg = "Please enter a valid email format (e.g. guest@example.com).";
    } else {
        $changeDue = round($amountPaid - $grandTotal, 2);
        $orderNumber = 'SB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $userId = current_user()['user_id'] ?? 1;

        mysqli_begin_transaction($conn);
        try {
            // 1. Insert order with customer metadata
            $sqlOrder = "INSERT INTO orders (order_number, user_id, customer_name, customer_email, subtotal_amount, tax_amount, order_status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, 'Completed', NOW())";
            $stmtOrder = mysqli_prepare($conn, $sqlOrder);
            mysqli_stmt_bind_param($stmtOrder, "sissdd", $orderNumber, $userId, $customerName, $customerEmail, $subtotalVatEx, $vatAmount);
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

                    // Fresh server-side anti-tamper recalculation of tier price and expiration check
                    $stRes = mysqli_query($conn, "
                        SELECT s.hall_id, st.tier_name, st.tier_surcharge, sc.base_price, sc.screening_date, sc.end_time, m.title AS movie_title
                        FROM seats s
                        INNER JOIN seat_tiers st ON s.tier_id = st.tier_id
                        INNER JOIN screenings sc ON sc.screening_id = {$scId}
                        INNER JOIN movies m ON sc.movie_id = m.movie_id
                        WHERE s.seat_id = {$seatId} LIMIT 1
                    ");
                    $stRow = mysqli_fetch_assoc($stRes);
                    if (!$stRow) {
                        throw new Exception("Invalid seat or screening reference in cart.");
                    }

                    // Check if screening concluded while customer was in cart/checkout
                    $scEndTime = strtotime($stRow['screening_date'] . ' ' . $stRow['end_time']);
                    if ($scEndTime && $scEndTime < time()) {
                        throw new Exception("Screening for '{$stRow['movie_title']}' has already concluded at " . date('g:i A', $scEndTime) . " and cannot be checked out.");
                    }

                    // Concurrency Protection: Check if seat is already booked for this screening
                    $checkSeatRes = mysqli_query($conn, "
                        SELECT ticket_id 
                        FROM tickets 
                        WHERE screening_id = {$scId} AND seat_id = {$seatId} AND ticket_status = 'Valid' 
                        LIMIT 1 FOR UPDATE
                    ");
                    if (mysqli_num_rows($checkSeatRes) > 0) {
                        throw new Exception("Seat " . ($item['seat_name'] ?? '#' . $seatId) . " is already reserved by another customer. Please choose another seat.");
                    }

                    $hallId = (int)$stRow['hall_id'];
                    $price = calculate_seat_price((float)$stRow['base_price'], (float)$stRow['tier_surcharge']);

                    mysqli_stmt_bind_param($stmtTicket, "iiiid", $orderId, $scId, $seatId, $hallId, $price);
                    mysqli_stmt_execute($stmtTicket);

                    // FR4: Release seat hold upon successful ticket generation
                    release_seat_hold($conn, $scId, $seatId);
                } elseif ($item['type'] === 'concession') {
                    $prodId = (int)$item['id'];
                    $qty = (int)$item['qty'];
                    $price = (float)$item['price'];

                    // Concurrency Protection: Lock product row with FOR UPDATE
                    $pStockRes = mysqli_query($conn, "SELECT current_stock, product_name FROM products WHERE product_id = {$prodId} LIMIT 1 FOR UPDATE");
                    $pStock = mysqli_fetch_assoc($pStockRes);
                    if (!$pStock || (int)$pStock['current_stock'] < $qty) {
                        $txAbortReason = "Insufficient stock for " . ($pStock['product_name'] ?? 'product');
                        break;
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

                    // Concurrency Protection: Deduct each constituent item in combo_items with FOR UPDATE
                    $ciRes = mysqli_query($conn, "SELECT product_id, quantity FROM combo_items WHERE combo_id = {$comboId}");
                    while ($child = mysqli_fetch_assoc($ciRes)) {
                        $childId = (int)$child['product_id'];
                        $childNeeded = (int)$child['quantity'] * $comboQty;

                        $pStockRes = mysqli_query($conn, "SELECT current_stock, product_name FROM products WHERE product_id = {$childId} LIMIT 1 FOR UPDATE");
                        $pStock = mysqli_fetch_assoc($pStockRes);
                        if (!$pStock || (int)$pStock['current_stock'] < $childNeeded) {
                            $txAbortReason = "Insufficient stock for constituent item " . ($pStock['product_name'] ?? 'product') . " in bundle.";
                            break 2;
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

            if (!empty($txAbortReason)) {
                mysqli_rollback($conn);
                $errorMsg = 'Transaction failed: ' . $txAbortReason;
            } else {
                // 4. Record payment
                $sqlPay = "INSERT INTO payments (order_id, payment_method, amount_paid, change_returned, payment_date)
                           VALUES (?, ?, ?, ?, NOW())";
                $stmtPay = mysqli_prepare($conn, $sqlPay);
                mysqli_stmt_bind_param($stmtPay, "isdd", $orderId, $paymentMethod, $amountPaid, $changeDue);
                mysqli_stmt_execute($stmtPay);
                mysqli_stmt_close($stmtPay);

                mysqli_commit($conn);

                // Term Test Lab: Attempt to dispatch digital PDF receipt via Mailtrap SMTP
                $emailSent = false;
                if (!empty($customerEmail) && validate_email($customerEmail)) {
                    $emailSent = send_transaction_email((int)$orderId, $customerEmail);
                }

                // Clear session cart and persist completed order details
                $_SESSION['completed_order'] = [
                    'order_id'       => (int)$orderId,
                    'order_number'   => $orderNumber,
                    'customer_name'  => $customerName,
                    'customer_email' => $customerEmail,
                    'email_sent'     => $emailSent,
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
            }
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $errorMsg = 'Transaction failed: ' . (mysqli_error($conn) ?: 'Please try again.');
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

        <?php if (!empty($ord['customer_email'])): ?>
            <div style="background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 0.85rem; margin-bottom: 1.5rem; text-align: center; font-size: 0.88rem; color: #3730a3;">
                <?php if (!empty($ord['email_sent'])): ?>
                    <span>📧 Digital PDF receipt was successfully dispatched to <strong><?= sanitize($ord['customer_email']); ?></strong> via Mailtrap.</span>
                <?php else: ?>
                    <span>📧 Customer receipt registered for <strong><?= sanitize($ord['customer_email']); ?></strong>.</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <?php if (!empty($ord['order_id'])): ?>
                <a href="<?= BASE_URL; ?>/receipt.php?order_id=<?= (int)$ord['order_id']; ?>" target="_blank" class="btn btn-primary" style="font-weight: 700;">
                    📄 Download Official PDF Receipt
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
            <a href="<?= BASE_URL; ?>/index.php" class="btn btn-secondary">Done / Back to Home</a>
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

    <?php if ($holdExpiryTimestamp && $holdExpiryTimestamp > time()): ?>
        <div class="seat-hold-banner" id="seatHoldBanner" data-expiry="<?= $holdExpiryTimestamp; ?>">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 1.5rem;">⏱️</span>
                <div>
                    <strong style="color: #f59e0b; font-size: 1rem; display: block;">Seats Held For Checkout</strong>
                    <span style="font-size: 0.85rem; color: var(--text-muted);">Complete transaction before the lock expires to avoid losing your seats.</span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Time Remaining:</span>
                <span class="seat-hold-timer" id="holdCountdownDisplay">--:--</span>
            </div>
        </div>
    <?php endif; ?>

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
                                    <span style="font-weight: 700; color: var(--c-ink, #1d1f3a); background: #f1f5f9; padding: 0.2rem 0.55rem; border-radius: 4px; font-size: 0.82rem; border: 1px solid var(--c-line, #dcdff0); display: inline-block;">
                                        <?= $qty; ?>
                                    </span>
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
            <form method="POST" action="<?= BASE_URL; ?>/checkout.php" id="checkoutPaymentForm" novalidate>
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
                    <label for="customer_name">Customer Name</label>
                    <input type="text" 
                           name="customer_name" 
                           id="customer_name" 
                           value="<?= sanitize(is_logged_in() ? current_user()['full_name'] : ''); ?>" 
                           placeholder="e.g. Maria Santos" 
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="customer_email">Customer Email <span style="font-weight: 400; color: var(--c-muted);">(for digital PDF receipt)</span></label>
                    <input type="text" 
                           name="customer_email" 
                           id="customer_email" 
                           placeholder="guest@example.com" 
                           class="form-control">
                    <small style="color: var(--c-muted, #5d6180); display: block; margin-top: 0.25rem;">
                        Official PDF receipt will be dispatched via Mailtrap SMTP.
                    </small>
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-control">
                        <option value="Cash">Cash</option>
                        <option value="E-Wallet">E-Wallet (GCash / Maya)</option>
                        <option value="Card">Debit / Credit Card</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount_paid">Amount Tendered (PHP)</label>
                    <input type="number" 
                           step="0.01" 
                           name="amount_paid" 
                           id="amount_paid" 
                           value="<?= $grandTotal; ?>" 
                           class="form-control">
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






    // Live Seat Hold Countdown Timer (FR4)
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

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
