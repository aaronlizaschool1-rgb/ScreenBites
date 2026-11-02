<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_login();

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    set_flash('error', 'Invalid order reference specified.');
    header("Location: " . BASE_URL . "/admin/orders.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_email') {
    $targetEmail = trim($_POST['customer_email'] ?? '');
    if (empty($targetEmail) || !validate_email($targetEmail)) {
        set_flash('error', 'Please provide a valid recipient email address.');
    } else {
        $sent = send_transaction_email($orderId, $targetEmail);
        if ($sent) {
            set_flash('success', "Transaction receipt successfully dispatched to {$targetEmail} via Mailtrap SMTP.");
        } else {
            set_flash('error', "Failed to dispatch email receipt. Please verify server SMTP configuration.");
        }
    }
    header("Location: " . BASE_URL . "/admin/order_details.php?id=" . $orderId);
    exit;
}

$stmt = mysqli_prepare($conn, "
    SELECT
        order_id,
        order_number,
        customer_name,
        customer_email,
        order_date,
        order_status,
        subtotal_amount,
        tax_amount,
        grand_total,
        user_id,
        processed_by_username,
        processed_by_name,
        processed_by_role,
        payment_id,
        payment_method,
        amount_paid,
        change_returned,
        payment_date,
        total_tickets,
        ticket_subtotal,
        total_concessions,
        concession_subtotal,
        total_combos,
        combo_subtotal
    FROM view_order_transaction_details
    WHERE order_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    set_flash('error', "Order #{$orderId} was not found.");
    header("Location: " . BASE_URL . "/admin/orders.php");
    exit;
}

$tickets = [];
$tStmt = mysqli_prepare($conn, "
    SELECT
        t.ticket_id,
        t.price_charged,
        t.ticket_status,
        m.title AS movie_title,
        m.rating AS movie_rating,
        h.hall_name,
        s.seat_row,
        s.seat_number,
        st.tier_name,
        sc.screening_date,
        sc.start_time
    FROM tickets t
    INNER JOIN screenings sc ON t.screening_id = sc.screening_id
    INNER JOIN movies m ON sc.movie_id = m.movie_id
    INNER JOIN halls h ON t.hall_id = h.hall_id
    INNER JOIN seats s ON t.seat_id = s.seat_id
    INNER JOIN seat_tiers st ON s.tier_id = st.tier_id
    WHERE t.order_id = ?
    ORDER BY s.seat_row ASC, s.seat_number ASC
");
mysqli_stmt_bind_param($tStmt, "i", $orderId);
mysqli_stmt_execute($tStmt);
$tRes = mysqli_stmt_get_result($tStmt);
while ($tRow = mysqli_fetch_assoc($tRes)) {
    $tickets[] = $tRow;
}
mysqli_stmt_close($tStmt);

$concessions = [];
$cStmt = mysqli_prepare($conn, "
    SELECT
        oi.order_item_id,
        p.product_name,
        p.category,
        oi.quantity,
        oi.unit_price_charged,
        (oi.quantity * oi.unit_price_charged) AS line_total
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
mysqli_stmt_bind_param($cStmt, "i", $orderId);
mysqli_stmt_execute($cStmt);
$cRes = mysqli_stmt_get_result($cStmt);
while ($cRow = mysqli_fetch_assoc($cRes)) {
    $concessions[] = $cRow;
}
mysqli_stmt_close($cStmt);

$combos = [];
$cbStmt = mysqli_prepare($conn, "
    SELECT
        oc.order_combo_id,
        cb.combo_name,
        oc.quantity,
        oc.unit_price_charged,
        (oc.quantity * oc.unit_price_charged) AS line_total
    FROM order_combos oc
    INNER JOIN combos cb ON oc.combo_id = cb.combo_id
    WHERE oc.order_id = ?
");
mysqli_stmt_bind_param($cbStmt, "i", $orderId);
mysqli_stmt_execute($cbStmt);
$cbRes = mysqli_stmt_get_result($cbStmt);
while ($cbRow = mysqli_fetch_assoc($cbRes)) {
    $combos[] = $cbRow;
}
mysqli_stmt_close($cbStmt);

$pageTitle = 'Order #' . $order['order_number'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <a href="<?= BASE_URL; ?>/admin/orders.php" style="font-size: 0.88rem; color: var(--c-muted); text-decoration: none; display: inline-block; margin-bottom: 0.35rem;">
            &larr; Back to Orders Ledger
        </a>
        <h1>Order #<?= sanitize($order['order_number']); ?></h1>
        <p>Comprehensive transaction audit powered by <code>view_order_transaction_details</code>.</p>
    </div>

    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="<?= BASE_URL; ?>/receipt.php?order_id=<?= $orderId; ?>" target="_blank" class="btn btn-primary">
            📄 Download Official PDF Receipt
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">

    <div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="padding: 1.25rem 1.25rem 0.5rem;">
                <h3 style="margin: 0; font-size: 1.1rem; color: var(--c-ink);">
                    🎟️ Box Office Admissions (<?= count($tickets); ?>)
                </h3>
            </div>
            <div class="sb-table-wrap">
                <table class="sb-table">
                    <thead>
                        <tr>
                            <th>Movie Title</th>
                            <th>Hall</th>
                            <th>Seat</th>
                            <th>Tier</th>
                            <th>Showtime</th>
                            <th style="text-align: right;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tickets)): ?>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td><strong><?= sanitize($t['movie_title']); ?></strong></td>
                                    <td><?= sanitize($t['hall_name']); ?></td>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 700; background: #eef2ff; color: #3730a3; padding: 0.2rem 0.5rem; border-radius: 4px;">
                                            <?= sanitize($t['seat_row'] . $t['seat_number']); ?>
                                        </span>
                                    </td>
                                    <td><?= sanitize($t['tier_name']); ?></td>
                                    <td>
                                        <?= date('M d, g:i A', strtotime($t['screening_date'] . ' ' . $t['start_time'])); ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 600;">
                                        <?= format_currency($t['price_charged']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--c-muted); padding: 1.5rem;">
                                    No movie tickets booked in this order.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div style="padding: 1.25rem 1.25rem 0.5rem;">
                <h3 style="margin: 0; font-size: 1.1rem; color: var(--c-ink);">
                    🍿 Snack Counter Items &amp; Bundles (<?= count($concessions) + count($combos); ?>)
                </h3>
            </div>
            <div class="sb-table-wrap">
                <table class="sb-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Unit Price</th>
                            <th style="text-align: right;">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($concessions) || !empty($combos)): ?>
                            <?php foreach ($concessions as $c): ?>
                                <tr>
                                    <td><strong><?= sanitize($c['product_name']); ?></strong></td>
                                    <td><?= sanitize($c['category']); ?></td>
                                    <td style="text-align: center;"><?= (int)$c['quantity']; ?></td>
                                    <td style="text-align: right;"><?= format_currency($c['unit_price_charged']); ?></td>
                                    <td style="text-align: right; font-weight: 600;"><?= format_currency($c['line_total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($combos as $cb): ?>
                                <tr>
                                    <td><strong><?= sanitize($cb['combo_name']); ?></strong></td>
                                    <td><span style="background: #fef3c7; color: #92400e; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600;">Bundle</span></td>
                                    <td style="text-align: center;"><?= (int)$cb['quantity']; ?></td>
                                    <td style="text-align: right;"><?= format_currency($cb['unit_price_charged']); ?></td>
                                    <td style="text-align: right; font-weight: 600;"><?= format_currency($cb['line_total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--c-muted); padding: 1.5rem;">
                                    No snack counter concessions ordered.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
            <h3 style="margin: 0 0 1rem; font-size: 1.1rem; color: var(--c-ink);">Transaction Summary</h3>

            <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--c-muted);">Date &amp; Time:</span>
                    <strong><?= date('M d, Y, g:i A', strtotime($order['order_date'])); ?></strong>
                </div>

                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--c-muted);">Status:</span>
                    <span style="color: #065f46; background: #d1fae5; font-size: 0.78rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px;">
                        <?= sanitize($order['order_status']); ?>
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--c-muted);">Processed By:</span>
                    <span><?= sanitize($order['processed_by_name'] ?? 'System'); ?> (<?= sanitize($order['processed_by_role'] ?? 'Staff'); ?>)</span>
                </div>

                <div style="border-top: 1px dashed var(--c-line); margin: 0.5rem 0;"></div>

                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--c-muted);">Customer:</span>
                    <strong><?= sanitize($order['customer_name'] ?? 'Walk-in Guest'); ?></strong>
                </div>

                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--c-muted);">Email:</span>
                    <span><?= sanitize($order['customer_email'] ?? 'Not provided'); ?></span>
                </div>

                <div style="border-top: 1px dashed var(--c-line); margin: 0.5rem 0;"></div>

                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--c-muted);">Vatable Subtotal:</span>
                    <span><?= format_currency($order['subtotal_amount']); ?></span>
                </div>

                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--c-muted);">VAT (12% Included):</span>
                    <span><?= format_currency($order['tax_amount']); ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 800; color: var(--c-indigo); border-top: 1px solid var(--c-line); padding-top: 0.6rem;">
                    <span>Grand Total:</span>
                    <span style="color: var(--accent-gold, #d97706);"><?= format_currency($order['grand_total']); ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 0.25rem;">
                    <span style="color: var(--c-muted);">Tender Method:</span>
                    <span><?= sanitize($order['payment_method'] ?? 'Cash'); ?></span>
                </div>

                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--c-muted);">Amount Tendered:</span>
                    <span><?= format_currency($order['amount_paid'] ?? $order['grand_total']); ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; color: #059669; font-weight: 600;">
                    <span>Change Returned:</span>
                    <span><?= format_currency($order['change_returned'] ?? 0.00); ?></span>
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem;">
            <h3 style="margin: 0 0 0.5rem; font-size: 1.05rem; color: var(--c-ink);">📧 Dispatch Digital Receipt</h3>
            <p style="margin: 0 0 1rem; color: var(--c-muted); font-size: 0.85rem;">Email official PDF receipt directly to the customer via Mailtrap.</p>

            <form method="POST" action="<?= BASE_URL; ?>/admin/order_details.php?id=<?= $orderId; ?>">
                <input type="hidden" name="action" value="resend_email">
                <div class="form-group" style="margin-bottom: 0.75rem;">
                    <label style="font-size: 0.8rem; font-weight: 600; color: var(--c-muted); display: block; margin-bottom: 0.25rem;">Recipient Email</label>
                    <input type="email"
                           name="customer_email"
                           value="<?= sanitize($order['customer_email'] ?? ''); ?>"
                           placeholder="customer@example.com"
                           class="form-control"
                           required>
                </div>
                <button type="submit" class="btn btn-secondary" style="width: 100%; font-weight: 600;">
                    Send Receipt via Mailtrap &rarr;
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
