<?php
/**
 * ScreenBites - Sales Report
 * Procedural reporting module modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Monthly Sales Report';
require_once dirname(__DIR__) . '/includes/header.php';

$selectedYear  = (int)($_GET['year'] ?? date('Y'));
$selectedMonth = (int)($_GET['month'] ?? date('n'));

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// 1. Overall Orders and Revenue
$orderRes = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS orders_count, 
        COALESCE(SUM(subtotal_amount + tax_amount), 0) AS gross_sales,
        COALESCE(SUM(subtotal_amount), 0) AS subtotal_sales,
        COALESCE(SUM(tax_amount), 0) AS tax_sales
    FROM orders 
    WHERE MONTH(created_at) = {$selectedMonth} 
      AND YEAR(created_at) = {$selectedYear} 
      AND order_status = 'Completed'
");
$orderData = mysqli_fetch_assoc($orderRes);
$grossSales  = (float)$orderData['gross_sales'];
$ordersCount = (int)$orderData['orders_count'];
$avgOrder    = $ordersCount > 0 ? ($grossSales / $ordersCount) : 0.00;

// 2. Ticket Sales Revenue
$ticketRes = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(t.price_charged), 0) AS ticket_revenue,
        COUNT(t.ticket_id) AS tickets_sold
    FROM tickets t
    INNER JOIN orders o ON t.order_id = o.order_id
    WHERE MONTH(o.created_at) = {$selectedMonth}
      AND YEAR(o.created_at) = {$selectedYear}
      AND o.order_status = 'Completed'
      AND t.ticket_status = 'Valid'
");
$ticketData = mysqli_fetch_assoc($ticketRes);
$ticketRevenue = (float)$ticketData['ticket_revenue'];
$ticketsSold   = (int)$ticketData['tickets_sold'];

// 3. Concession Sales Revenue (Snacks + Combos)
$concRes = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(oi.quantity * oi.unit_price_charged), 0) AS concession_revenue,
        COALESCE(SUM(oi.quantity), 0) AS items_sold
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.order_id
    WHERE MONTH(o.created_at) = {$selectedMonth}
      AND YEAR(o.created_at) = {$selectedYear}
      AND o.order_status = 'Completed'
");
$concData = mysqli_fetch_assoc($concRes);

$comboSalesRes = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(oc.quantity * oc.unit_price_charged), 0) AS combo_revenue,
        COALESCE(SUM(oc.quantity), 0) AS combos_sold
    FROM order_combos oc
    INNER JOIN orders o ON oc.order_id = o.order_id
    WHERE MONTH(o.created_at) = {$selectedMonth}
      AND YEAR(o.created_at) = {$selectedYear}
      AND o.order_status = 'Completed'
");
$comboSalesData = mysqli_fetch_assoc($comboSalesRes);

$concRevenue = (float)$concData['concession_revenue'] + (float)$comboSalesData['combo_revenue'];
$itemsSold   = (int)$concData['items_sold'] + (int)$comboSalesData['combos_sold'];

// Revenue Shares
$ticketShare = $grossSales > 0 ? round(($ticketRevenue / $grossSales) * 100, 1) : 0;
$concShare   = $grossSales > 0 ? round(($concRevenue / $grossSales) * 100, 1) : 0;

// 4. Payment Method Breakdown
$payRes = mysqli_query($conn, "
    SELECT 
        p.payment_method, 
        COUNT(*) AS tx_count, 
        COALESCE(SUM(p.amount_paid - p.change_returned), 0) AS net_collected
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.order_id
    WHERE MONTH(o.created_at) = {$selectedMonth}
      AND YEAR(o.created_at) = {$selectedYear}
      AND o.order_status = 'Completed'
    GROUP BY p.payment_method
");

// 5. Daily Breakdown
$dailyRes = mysqli_query($conn, "
    SELECT 
        DATE(created_at) AS sale_date,
        COUNT(*) AS daily_orders,
        COALESCE(SUM(subtotal_amount + tax_amount), 0) AS daily_gross
    FROM orders
    WHERE MONTH(created_at) = {$selectedMonth}
      AND YEAR(created_at) = {$selectedYear}
      AND order_status = 'Completed'
    GROUP BY DATE(created_at)
    ORDER BY sale_date DESC
");
?>

<div class="page-head">
    <div>
        <h1>Monthly Sales Report</h1>
        <p>Revenue breakdown and transaction volume for <?= $monthNames[$selectedMonth] . ' ' . $selectedYear; ?>.</p>
    </div>
    
    <!-- Filter form -->
    <form method="GET" action="<?= BASE_URL; ?>/reports/sales.php" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <select name="month" class="form-control" style="width: auto; padding: 0.4rem 0.6rem;">
            <?php foreach ($monthNames as $mNum => $mName): ?>
                <option value="<?= $mNum; ?>" <?= $mNum === $selectedMonth ? 'selected' : ''; ?>><?= $mName; ?></option>
            <?php endforeach; ?>
        </select>

        <select name="year" class="form-control" style="width: auto; padding: 0.4rem 0.6rem;">
            <?php for ($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
                <option value="<?= $y; ?>" <?= $y === $selectedYear ? 'selected' : ''; ?>><?= $y; ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary">Generate Report</button>
    </form>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Gross Revenue</p>
        <p class="kpi-value" style="color: var(--c-indigo);"><?= format_currency($grossSales); ?></p>
        <p class="kpi-note"><?= number_format($ordersCount); ?> completed sales orders</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Ticket Revenue</p>
        <p class="kpi-value"><?= format_currency($ticketRevenue); ?></p>
        <p class="kpi-note"><?= number_format($ticketsSold); ?> seats booked (<?= $ticketShare; ?>%)</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Concessions Revenue</p>
        <p class="kpi-value"><?= format_currency($concRevenue); ?></p>
        <p class="kpi-note"><?= number_format($itemsSold); ?> snacks sold (<?= $concShare; ?>%)</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Average Order Value</p>
        <p class="kpi-value" style="color: #0284c7;"><?= format_currency($avgOrder); ?></p>
        <p class="kpi-note">Spend per guest checkout</p>
    </div>
</div>

<!-- Revenue Split & Payment Method Breakdown -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <!-- Revenue Stream Breakdown -->
    <div class="card" style="padding: 1.25rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1.1rem; color: var(--c-ink);">Revenue Stream Split</h3>
        
        <div style="margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.35rem;">
                <span>🎟️ Box Office Tickets</span>
                <span><?= format_currency($ticketRevenue); ?> (<?= $ticketShare; ?>%)</span>
            </div>
            <div style="width: 100%; height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden;">
                <div style="width: <?= $ticketShare; ?>%; height: 100%; background: var(--c-indigo);"></div>
            </div>
        </div>

        <div>
            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.35rem;">
                <span>🍿 Snack Concessions</span>
                <span><?= format_currency($concRevenue); ?> (<?= $concShare; ?>%)</span>
            </div>
            <div style="width: 100%; height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden;">
                <div style="width: <?= $concShare; ?>%; height: 100%; background: var(--c-gold);"></div>
            </div>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="card" style="padding: 1.25rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1.1rem; color: var(--c-ink);">Tender &amp; Payment Methods</h3>
        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
            <?php if ($payRes && mysqli_num_rows($payRes) > 0): ?>
                <?php while ($p = mysqli_fetch_assoc($payRes)): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: #f8fafc; border: 1px solid var(--c-line); border-radius: 6px;">
                        <span style="font-weight: 600;"><?= sanitize($p['payment_method']); ?></span>
                        <div style="text-align: right;">
                            <strong><?= format_currency($p['net_collected']); ?></strong>
                            <small style="color: var(--c-muted); display: block; font-size: 0.75rem;"><?= (int)$p['tx_count']; ?> transactions</small>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: var(--c-muted); font-size: 0.9rem;">No payments collected this period.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Daily Sales Ledger -->
<div class="card">
    <div style="padding: 1.25rem 1.25rem 0.5rem;">
        <h3 style="margin: 0; font-size: 1.1rem; color: var(--c-ink);">Daily Sales Journal</h3>
        <p style="margin: 0.2rem 0 1rem; color: var(--c-muted); font-size: 0.85rem;">Daily gross sales volume for the selected month.</p>
    </div>
    
    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Completed Orders</th>
                    <th>Daily Gross Total</th>
                    <th>Average Ticket / Order</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($dailyRes && mysqli_num_rows($dailyRes) > 0): ?>
                    <?php while ($d = mysqli_fetch_assoc($dailyRes)): 
                        $dGross = (float)$d['daily_gross'];
                        $dOrders = (int)$d['daily_orders'];
                        $dAvg = $dOrders > 0 ? ($dGross / $dOrders) : 0;
                    ?>
                        <tr>
                            <td>
                                <strong><?= date('l, F j, Y', strtotime($d['sale_date'])); ?></strong>
                            </td>
                            <td><?= number_format($dOrders); ?> orders</td>
                            <td>
                                <strong style="color: var(--c-indigo); font-family: 'Bricolage Grotesque', sans-serif;">
                                    <?= format_currency($dGross); ?>
                                </strong>
                            </td>
                            <td style="color: var(--c-muted); font-size: 0.88rem;">
                                <?= format_currency($dAvg); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--c-muted); padding: 2.5rem;">
                            No sales transactions recorded for <?= $monthNames[$selectedMonth] . ' ' . $selectedYear; ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
