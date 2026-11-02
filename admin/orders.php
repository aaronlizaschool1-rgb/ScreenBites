<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    require_admin();

    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');
    $allowedStatuses = ['Pending', 'Paid', 'Completed', 'Cancelled', 'Refunded'];

    if ($orderId > 0 && in_array($newStatus, $allowedStatuses, true)) {
        $chkStmt = mysqli_prepare($conn, "SELECT order_id, order_number, customer_name, customer_email, order_status FROM orders WHERE order_id = ? LIMIT 1");
        if ($chkStmt) {
            mysqli_stmt_bind_param($chkStmt, "i", $orderId);
            mysqli_stmt_execute($chkStmt);
            $ordData = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));
            mysqli_stmt_close($chkStmt);

            if ($ordData) {
                $oldStatus = $ordData['order_status'];

                $upStmt = mysqli_prepare($conn, "UPDATE orders SET order_status = ? WHERE order_id = ?");
                if ($upStmt) {
                    mysqli_stmt_bind_param($upStmt, "si", $newStatus, $orderId);
                    $ok = mysqli_stmt_execute($upStmt);
                    mysqli_stmt_close($upStmt);

                    if ($ok) {
                        audit_log('ORDER_STATUS_UPDATED', 'orders', $orderId, "Updated order #{$ordData['order_number']} status from '{$oldStatus}' to '{$newStatus}'");

                        $emailSent = false;
                        if (!empty($ordData['customer_email']) && filter_var($ordData['customer_email'], FILTER_VALIDATE_EMAIL)) {
                            require_once dirname(__DIR__) . '/includes/mailer.php';
                            $statusNote = "Your order status was updated from {$oldStatus} to {$newStatus} by cinema administration.";
                            $emailSent = send_transaction_email($orderId, $ordData['customer_email'], [], $statusNote);
                        }

                        $flashMsg = "Order #{$ordData['order_number']} status updated to '{$newStatus}'.";
                        if ($emailSent) {
                            $flashMsg .= " Customer status update notification & PDF receipt dispatched via Mailtrap SMTP.";
                        }
                        set_flash('success', $flashMsg);
                    } else {
                        set_flash('error', "Database error updating order status: " . mysqli_error($conn));
                    }
                }
            } else {
                set_flash('error', "Order record not found.");
            }
        }
    } else {
        set_flash('error', "Invalid order identifier or status parameter.");
    }

    $retQuery = !empty($_POST['return_query']) ? '?' . $_POST['return_query'] : '';
    header("Location: " . BASE_URL . "/admin/orders.php" . $retQuery);
    exit;
}

$pageTitle = 'Orders & Transactions';
require_once dirname(__DIR__) . '/includes/header.php';

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$paymentFilter = trim($_GET['payment_method'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR processed_by_name LIKE ?)";
    $likeSearch = "%" . $search . "%";
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $types .= "ssss";
}

if (!empty($statusFilter)) {
    $where[] = "order_status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($paymentFilter)) {
    $where[] = "payment_method = ?";
    $params[] = $paymentFilter;
    $types .= "s";
}

if (!empty($dateFrom)) {
    $where[] = "DATE(order_date) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if (!empty($dateTo)) {
    $where[] = "DATE(order_date) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

$whereClause = implode(" AND ", $where);

$kpiSql = "SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(grand_total), 0) AS gross_sales,
            COALESCE(SUM(total_tickets), 0) AS sum_tickets,
            COALESCE(SUM(total_concessions + total_combos), 0) AS sum_snacks
           FROM view_order_transaction_details
           WHERE {$whereClause}";

$kpiStmt = mysqli_prepare($conn, $kpiSql);
if (!empty($types)) {
    mysqli_stmt_bind_param($kpiStmt, $types, ...$params);
}
mysqli_stmt_execute($kpiStmt);
$kpiData = mysqli_fetch_assoc(mysqli_stmt_get_result($kpiStmt));
mysqli_stmt_close($kpiStmt);

$totalOrders = (int)($kpiData['total_count'] ?? 0);
$grossSales = (float)($kpiData['gross_sales'] ?? 0.00);
$totalTickets = (int)($kpiData['sum_tickets'] ?? 0);
$totalSnacks = (int)($kpiData['sum_snacks'] ?? 0);

$listSql = "SELECT
                order_id,
                order_number,
                customer_name,
                customer_email,
                order_date,
                order_status,
                subtotal_amount,
                tax_amount,
                grand_total,
                processed_by_name,
                processed_by_role,
                payment_method,
                amount_paid,
                change_returned,
                total_tickets,
                total_concessions,
                total_combos
            FROM view_order_transaction_details
            WHERE {$whereClause}
            ORDER BY order_date DESC
            LIMIT 100";

$listStmt = mysqli_prepare($conn, $listSql);
if (!empty($types)) {
    mysqli_stmt_bind_param($listStmt, $types, ...$params);
}
mysqli_stmt_execute($listStmt);
$ordersRes = mysqli_stmt_get_result($listStmt);
$ordersList = [];
while ($row = mysqli_fetch_assoc($ordersRes)) {
    $ordersList[] = $row;
}
mysqli_stmt_close($listStmt);
?>

<div class="page-head">
    <div>
        <h1>Orders &amp; Transactions Ledger</h1>
        <p>Operational sales audit powered by MySQL view <code>view_order_transaction_details</code>.</p>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Gross Transaction Volume</p>
        <p class="kpi-value" style="color: var(--c-indigo);"><?= format_currency($grossSales); ?></p>
        <p class="kpi-note"><?= number_format($totalOrders); ?> matching orders</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Tickets Issued</p>
        <p class="kpi-value" style="color: var(--c-indigo);"><?= number_format($totalTickets); ?></p>
        <p class="kpi-note">Cinema admissions reserved</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Concessions Dispensed</p>
        <p class="kpi-value" style="color: var(--accent-gold, #f59e0b);"><?= number_format($totalSnacks); ?></p>
        <p class="kpi-note">Snacks &amp; combo bundles</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Average Order Value</p>
        <p class="kpi-value" style="color: #0284c7;">
            <?= format_currency($totalOrders > 0 ? ($grossSales / $totalOrders) : 0); ?>
        </p>
        <p class="kpi-note">Gross per transaction</p>
    </div>
</div>

<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="<?= BASE_URL; ?>/admin/orders.php" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 220px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--c-muted); display: block; margin-bottom: 0.25rem;">Search Order, Customer, or Staff</label>
            <input type="text" name="search" value="<?= sanitize($search); ?>" placeholder="e.g. SB-20261101, John, customer@email..." class="form-control">
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--c-muted); display: block; margin-bottom: 0.25rem;">Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Paid" <?= $statusFilter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                <option value="Refunded" <?= $statusFilter === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--c-muted); display: block; margin-bottom: 0.25rem;">Payment Method</label>
            <select name="payment_method" class="form-control">
                <option value="">All Methods</option>
                <option value="Cash" <?= $paymentFilter === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="E-Wallet" <?= $paymentFilter === 'E-Wallet' ? 'selected' : ''; ?>>E-Wallet</option>
                <option value="Card" <?= $paymentFilter === 'Card' ? 'selected' : ''; ?>>Card</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--c-muted); display: block; margin-bottom: 0.25rem;">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom); ?>" class="form-control">
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--c-muted); display: block; margin-bottom: 0.25rem;">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo); ?>" class="form-control">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">Filter</button>
            <a href="<?= BASE_URL; ?>/admin/orders.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div style="padding: 1.25rem 1.25rem 0.5rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin: 0; font-size: 1.15rem; color: var(--c-ink);">Transaction Records (<?= count($ordersList); ?>)</h3>
            <p style="margin: 0.2rem 0 0; color: var(--c-muted); font-size: 0.85rem;">Showing latest transactions from relational view.</p>
        </div>
    </div>

    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th>Order Reference</th>
                    <th>Date &amp; Time</th>
                    <th>Customer</th>
                    <th>Staff / Cashier</th>
                    <th style="text-align: center;">Items</th>
                    <th style="text-align: right;">Grand Total</th>
                    <th>Payment</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($ordersList)): ?>
                    <?php foreach ($ordersList as $ord):
                        $grand = (float)$ord['grand_total'];
                        $tix = (int)$ord['total_tickets'];
                        $snacks = (int)$ord['total_concessions'] + (int)$ord['total_combos'];
                    ?>
                        <tr>
                            <td>
                                <strong style="font-family: monospace; color: var(--c-indigo); font-size: 0.95rem;">
                                    #<?= sanitize($ord['order_number']); ?>
                                </strong>
                            </td>
                            <td>
                                <span style="font-size: 0.88rem; font-weight: 500; display: block;">
                                    <?= date('M d, Y', strtotime($ord['order_date'])); ?>
                                </span>
                                <small style="color: var(--c-muted); font-size: 0.78rem;">
                                    <?= date('g:i A', strtotime($ord['order_date'])); ?>
                                </small>
                            </td>
                            <td>
                                <span style="font-weight: 600; font-size: 0.9rem; display: block;">
                                    <?= sanitize($ord['customer_name'] ?? 'Walk-in Guest'); ?>
                                </span>
                                <?php if (!empty($ord['customer_email'])): ?>
                                    <small style="color: var(--c-muted); font-size: 0.78rem;">
                                        <?= sanitize($ord['customer_email']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 0.88rem;">
                                    <?= sanitize($ord['processed_by_name'] ?? 'System'); ?>
                                </span>
                                <small style="display: block; color: var(--c-muted); font-size: 0.75rem;">
                                    <?= sanitize($ord['processed_by_role'] ?? ''); ?>
                                </small>
                            </td>
                            <td style="text-align: center; font-size: 0.85rem;">
                                <?php if ($tix > 0): ?>
                                    <span style="display: inline-block; background: #eef2ff; color: #3730a3; padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 600; margin: 1px;">
                                        🎟️ <?= $tix; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($snacks > 0): ?>
                                    <span style="display: inline-block; background: #fef3c7; color: #92400e; padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 600; margin: 1px;">
                                        🍿 <?= $snacks; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($tix === 0 && $snacks === 0): ?>
                                    <span style="color: var(--c-muted);">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--c-ink); font-size: 0.95rem;">
                                <?= format_currency($grand); ?>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 4px;">
                                    <?= sanitize($ord['payment_method'] ?? 'Cash'); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if (is_admin()):
                                    $st = $ord['order_status'];
                                    $bgColor = '#f1f5f9';
                                    $textColor = '#475569';
                                    if ($st === 'Paid' || $st === 'Completed') {
                                        $bgColor = '#d1fae5';
                                        $textColor = '#065f46';
                                    } elseif ($st === 'Cancelled') {
                                        $bgColor = '#fee2e2';
                                        $textColor = '#991b1b';
                                    } elseif ($st === 'Pending') {
                                        $bgColor = '#fef3c7';
                                        $textColor = '#92400e';
                                    }
                                ?>
                                    <form method="POST" action="<?= BASE_URL; ?>/admin/orders.php" style="margin: 0; display: inline-block;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= (int)$ord['order_id']; ?>">
                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? ''); ?>">
                                        <select name="new_status" onchange="this.form.submit()" style="font-size: 0.78rem; font-weight: 700; padding: 0.2rem 0.45rem; border-radius: 6px; border: 1px solid var(--c-line); background: <?= $bgColor; ?>; color: <?= $textColor; ?>; cursor: pointer;">
                                            <option value="Pending" <?= $st === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Paid" <?= $st === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="Completed" <?= $st === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled" <?= $st === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="Refunded" <?= $st === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #065f46; background: #d1fae5; font-size: 0.78rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 999px;">
                                        <?= sanitize($ord['order_status']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 0.35rem;">
                                    <a href="<?= BASE_URL; ?>/admin/order_details.php?id=<?= (int)$ord['order_id']; ?>"
                                       class="btn btn-sm btn-secondary"
                                       title="View Full Breakdown">
                                        Details
                                    </a>
                                    <a href="<?= BASE_URL; ?>/receipt.php?order_id=<?= (int)$ord['order_id']; ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-primary"
                                       title="Download Official PDF Receipt">
                                        PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 3rem; color: var(--c-muted);">
                            No transaction records matched the selected filters.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
