<?php
/**
 * ScreenBites - Checkout & Order Processing
 * Modeled after itim211-2b-shop/checkout.php
 */

require_once __DIR__ . '/includes/config.php';

$cart = $_SESSION['cart'] ?? [];

// Calculate totals
$grandTotal = 0.00;
$hasTickets = false;
$hasConcessions = false;

foreach ($cart as $item) {
    $grandTotal += ((float)$item['price'] * (int)$item['qty']);
    if ($item['type'] === 'ticket') {
        $hasTickets = true;
    } elseif ($item['type'] === 'concession') {
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

            $sqlDecStock = "UPDATE products SET current_stock = current_stock - ? WHERE product_id = ?";
            $stmtDecStock = mysqli_prepare($conn, $sqlDecStock);

            foreach ($cart as $item) {
                if ($item['type'] === 'ticket') {
                    $scId = (int)$item['screening_id'];
                    $seatId = (int)$item['seat_id'];
                    $price = (float)$item['price'];

                    // Get hall_id
                    $hRes = mysqli_query($conn, "SELECT hall_id FROM screenings WHERE screening_id = {$scId} LIMIT 1");
                    $hRow = mysqli_fetch_assoc($hRes);
                    $hallId = (int)($hRow['hall_id'] ?? 1);

                    mysqli_stmt_bind_param($stmtTicket, "iiiid", $orderId, $scId, $seatId, $hallId, $price);
                    mysqli_stmt_execute($stmtTicket);
                } elseif ($item['type'] === 'concession') {
                    $prodId = (int)$item['id'];
                    $qty = (int)$item['qty'];
                    $price = (float)$item['price'];

                    mysqli_stmt_bind_param($stmtOrderItem, "iiid", $orderId, $prodId, $qty, $price);
                    mysqli_stmt_execute($stmtOrderItem);

                    mysqli_stmt_bind_param($stmtDecStock, "ii", $qty, $prodId);
                    mysqli_stmt_execute($stmtDecStock);
                }
            }
            mysqli_stmt_close($stmtTicket);
            mysqli_stmt_close($stmtOrderItem);
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
        <div style="text-align: center; border-bottom: 2px dashed var(--card-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
            <h1 style="font-size: 1.6rem; color: var(--accent-gold); margin-bottom: 0.25rem;">ScreenBites Cinema</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Official Digital Sales Receipt</p>
            <p style="font-family: monospace; font-weight: 700; margin-top: 0.5rem; font-size: 1.1rem; color: #ffffff;">
                Order #<?= sanitize($ord['order_number']); ?>
            </p>
            <p style="color: var(--text-muted); font-size: 0.85rem;"><?= sanitize($ord['date']); ?></p>
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
                            <strong><?= sanitize($it['name']); ?></strong><br>
                            <small style="color: var(--text-muted);"><?= sanitize($it['details'] ?? ''); ?></small>
                        </td>
                        <td style="text-align: center;"><?= (int)$it['qty']; ?></td>
                        <td style="text-align: right;"><?= format_currency((float)$it['price'] * (int)$it['qty']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="background: #0e1017; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: var(--text-muted); font-size: 0.9rem;">
                <span>Vatable Subtotal:</span>
                <span><?= format_currency($ord['vat_subtotal']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: var(--text-muted); font-size: 0.9rem;">
                <span>VAT (12%):</span>
                <span><?= format_currency($ord['vat_amount']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; color: #ffffff; border-top: 1px solid var(--card-border); padding-top: 0.5rem;">
                <span>Total Paid:</span>
                <span style="color: var(--accent-gold);"><?= format_currency($ord['grand_total']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-muted);">
                <span>Tender (<?= sanitize($ord['payment_method']); ?>):</span>
                <span><?= format_currency($ord['amount_paid']); ?></span>
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

    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start;">
        <div class="sb-card">
            <h2 style="font-size: 1.2rem; margin-bottom: 1rem;">Order Items Summary</h2>
            <table class="sb-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $item): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($item['name']); ?></strong><br>
                                <small style="color: var(--text-muted);"><?= sanitize($item['details'] ?? ''); ?></small>
                            </td>
                            <td style="text-align: center;"><?= (int)$item['qty']; ?></td>
                            <td style="text-align: right; font-weight: 600;">
                                <?= format_currency((float)$item['price'] * (int)$item['qty']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sb-card">
            <h2 style="font-size: 1.2rem; margin-bottom: 1rem;">Payment Details</h2>
            <form method="POST" action="<?= BASE_URL; ?>/checkout.php">
                <div style="background: #0e1017; padding: 1rem; border-radius: 6px; margin-bottom: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: var(--text-muted); font-size: 0.85rem;">
                        <span>Vatable Subtotal:</span>
                        <span><?= format_currency($subtotalVatEx); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: var(--text-muted); font-size: 0.85rem;">
                        <span>VAT (12%):</span>
                        <span><?= format_currency($vatAmount); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 700; color: #ffffff; border-top: 1px solid var(--card-border); padding-top: 0.5rem;">
                        <span>Grand Total:</span>
                        <span style="color: var(--accent-gold);"><?= format_currency($grandTotal); ?></span>
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
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                    Confirm & Complete Order
                </button>
            </form>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
