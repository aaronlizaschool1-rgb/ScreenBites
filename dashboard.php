<?php
/**
 * ScreenBites - Admin Operational Dashboard
 * High-level executive overview modeled after itim211-2b-shop
 */

require_once __DIR__ . '/includes/config.php';
require_admin();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/includes/header.php';

// 1. Cinema Sales & Volume KPIs
$resGross = mysqli_query($conn, "SELECT COALESCE(SUM(subtotal_amount + tax_amount), 0) AS total_gross, COUNT(*) AS total_orders FROM orders WHERE order_status = 'Completed'");
$grossRow = mysqli_fetch_assoc($resGross);
$grossRevenue = (float)$grossRow['total_gross'];
$totalOrders  = (int)$grossRow['total_orders'];

$resTickets = mysqli_query($conn, "SELECT COUNT(*) AS total_tickets FROM tickets WHERE ticket_status = 'Valid'");
$ticketsRow = mysqli_fetch_assoc($resTickets);
$totalTickets = (int)$ticketsRow['total_tickets'];

$resSnacks = mysqli_query($conn, "SELECT COALESCE(SUM(quantity), 0) AS total_snacks FROM order_items");
$snacksRow = mysqli_fetch_assoc($resSnacks);
$totalSnacks = (int)$snacksRow['total_snacks'];

// 2. Utility Overhead for Current Year
$currentYear = (int)date('Y');
$resUtilTotals = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(bill_amount), 0) AS total_utilities,
        COALESCE(SUM(CASE WHEN utility_type = 'Electricity' THEN bill_amount ELSE 0 END), 0) AS total_electricity,
        COALESCE(SUM(CASE WHEN utility_type = 'Water' THEN bill_amount ELSE 0 END), 0) AS total_water
    FROM utility_expenses 
    WHERE billing_year = {$currentYear}
");
$utilTotals = mysqli_fetch_assoc($resUtilTotals);
$totalUtilities   = (float)$utilTotals['total_utilities'];
$totalElectricity = (float)$utilTotals['total_electricity'];
$totalWater       = (float)$utilTotals['total_water'];

$netOperatingMargin = $grossRevenue - $totalUtilities;

// 3. Recent 5 Orders
$recentOrdersRes = mysqli_query($conn, "
    SELECT o.order_id, o.order_number, (o.subtotal_amount + o.tax_amount) AS total_amount, o.created_at, u.full_name AS cashier_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    WHERE o.order_status = 'Completed'
    ORDER BY o.created_at DESC
    LIMIT 5
");
?>

<div class="page-head">
    <div>
        <h1>Operational Dashboard</h1>
        <p>Executive overview of box office revenue, guest volume, and operational health for <?= $currentYear; ?>.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= BASE_URL; ?>/reports/sales.php" class="btn btn-primary">View Sales Report</a>
        <a href="<?= BASE_URL; ?>/utilities/index.php" class="btn btn-secondary">Manage Utilities</a>
    </div>
</div>

<!-- Cinema Operational KPIs -->
<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Gross Box Office Sales</p>
        <p class="kpi-value" style="color: var(--c-indigo);"><?= format_currency($grossRevenue); ?></p>
        <p class="kpi-note"><?= number_format($totalOrders); ?> completed transactions</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Tickets &amp; Concessions</p>
        <p class="kpi-value"><?= number_format($totalTickets); ?> <span style="font-size: 1rem; color: var(--c-muted); font-weight: 500;">Seats</span></p>
        <p class="kpi-note"><?= number_format($totalSnacks); ?> snack items sold</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Utility Overhead (<?= $currentYear; ?>)</p>
        <p class="kpi-value" style="color: var(--c-red);"><?= format_currency($totalUtilities); ?></p>
        <p class="kpi-note">⚡ Electricity + 💧 Water</p>
    </div>

    <div class="kpi" style="border-top: 4px solid <?= $netOperatingMargin >= 0 ? '#2f9e55' : 'var(--c-red)'; ?>;">
        <p class="kpi-label">Net Operating Margin</p>
        <p class="kpi-value" style="color: <?= $netOperatingMargin >= 0 ? '#1b5e35' : 'var(--c-red)'; ?>;">
            <?= format_currency($netOperatingMargin); ?>
        </p>
        <p class="kpi-note">Gross Sales &minus; Utilities Overhead</p>
    </div>
</div>

<!-- Utility Breakdown Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 0.85rem; color: var(--c-indigo); font-weight: 700; text-transform: uppercase;">⚡ Electricity Overhead (<?= $currentYear; ?>)</div>
            <div style="font-size: 1.5rem; font-weight: 800; font-family: 'Bricolage Grotesque', sans-serif; color: var(--c-ink); margin-top: 0.25rem;"><?= format_currency($totalElectricity); ?></div>
            <div style="font-size: 0.8rem; color: var(--c-muted); margin-top: 0.25rem;">Projectors, sound processors &amp; central HVAC</div>
        </div>
        <span style="font-size: 2.2rem; opacity: 0.6;">⚡</span>
    </div>

    <div class="card" style="padding: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 0.85rem; color: #0284c7; font-weight: 700; text-transform: uppercase;">💧 Water Overhead (<?= $currentYear; ?>)</div>
            <div style="font-size: 1.5rem; font-weight: 800; font-family: 'Bricolage Grotesque', sans-serif; color: var(--c-ink); margin-top: 0.25rem;"><?= format_currency($totalWater); ?></div>
            <div style="font-size: 0.8rem; color: var(--c-muted); margin-top: 0.25rem;">Restrooms sanitation &amp; concessions washdown</div>
        </div>
        <span style="font-size: 2.2rem; opacity: 0.6;">💧</span>
    </div>
</div>

<!-- Two-Column Layout: Quick Financial Modules & Recent Completed Orders -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: start;">
    
    <!-- Left: Quick Reporting Shortcuts -->
    <div class="card" style="padding: 1.25rem;">
        <h3 style="margin: 0 0 0.5rem; font-size: 1.15rem; color: var(--c-ink);">Financial &amp; System Navigation</h3>
        <p style="margin: 0 0 1rem; color: var(--c-muted); font-size: 0.85rem;">Access dedicated analytical ledgers and compliance tools.</p>

        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
            <a href="<?= BASE_URL; ?>/utilities/index.php" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #fafbfe; border: 1px solid var(--c-line); border-radius: 8px; text-decoration: none; color: inherit; transition: background 0.15s ease;">
                <div>
                    <strong style="color: var(--c-indigo); font-size: 0.95rem;">⚡ Utilities &amp; Expenses Ledger</strong>
                    <small style="display: block; color: var(--c-muted); font-size: 0.8rem;">Monthly electricity &amp; water bill auditor</small>
                </div>
                <span style="color: var(--c-muted); font-weight: 700;">&rarr;</span>
            </a>

            <a href="<?= BASE_URL; ?>/reports/sales.php" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #fafbfe; border: 1px solid var(--c-line); border-radius: 8px; text-decoration: none; color: inherit; transition: background 0.15s ease;">
                <div>
                    <strong style="color: var(--c-indigo); font-size: 0.95rem;">💵 Monthly Sales Report</strong>
                    <small style="display: block; color: var(--c-muted); font-size: 0.8rem;">Tickets vs. concessions split and daily trends</small>
                </div>
                <span style="color: var(--c-muted); font-weight: 700;">&rarr;</span>
            </a>

            <a href="<?= BASE_URL; ?>/reports/financial.php" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #fafbfe; border: 1px solid var(--c-line); border-radius: 8px; text-decoration: none; color: inherit; transition: background 0.15s ease;">
                <div>
                    <strong style="color: var(--c-indigo); font-size: 0.95rem;">📄 Net Income (P&amp;L Statement)</strong>
                    <small style="display: block; color: var(--c-muted); font-size: 0.8rem;">Revenue minus COGS and operating overhead</small>
                </div>
                <span style="color: var(--c-muted); font-weight: 700;">&rarr;</span>
            </a>

            <a href="<?= BASE_URL; ?>/reports/occupancy.php" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #fafbfe; border: 1px solid var(--c-line); border-radius: 8px; text-decoration: none; color: inherit; transition: background 0.15s ease;">
                <div>
                    <strong style="color: var(--c-indigo); font-size: 0.95rem;">📊 Occupancy Analytics</strong>
                    <small style="display: block; color: var(--c-muted); font-size: 0.8rem;">Theater seat fill rates and peak showtimes</small>
                </div>
                <span style="color: var(--c-muted); font-weight: 700;">&rarr;</span>
            </a>

            <a href="<?= BASE_URL; ?>/audit/index.php" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #fafbfe; border: 1px solid var(--c-line); border-radius: 8px; text-decoration: none; color: inherit; transition: background 0.15s ease;">
                <div>
                    <strong style="color: var(--c-indigo); font-size: 0.95rem;">🛡️ Security &amp; Audit Logs</strong>
                    <small style="display: block; color: var(--c-muted); font-size: 0.8rem;">Compliance history and operator action audit trail</small>
                </div>
                <span style="color: var(--c-muted); font-weight: 700;">&rarr;</span>
            </a>
        </div>
    </div>

    <!-- Right: Recent Completed Orders -->
    <div class="card" style="padding: 1.25rem;">
        <h3 style="margin: 0 0 0.5rem; font-size: 1.15rem; color: var(--c-ink);">Recent Sales Transactions</h3>
        <p style="margin: 0 0 1rem; color: var(--c-muted); font-size: 0.85rem;">Latest completed customer checkouts.</p>

        <div class="sb-table-wrap">
            <table class="sb-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Time</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentOrdersRes && mysqli_num_rows($recentOrdersRes) > 0): ?>
                        <?php while ($ord = mysqli_fetch_assoc($recentOrdersRes)): ?>
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; font-size: 0.85rem; color: var(--c-indigo);">
                                        <?= sanitize($ord['order_number']); ?>
                                    </strong>
                                    <small style="display: block; color: var(--c-muted); font-size: 0.75rem;">By: <?= sanitize($ord['cashier_name'] ?? 'Cashier'); ?></small>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--c-muted); white-space: nowrap;">
                                    <?= date('M d, g:i A', strtotime($ord['created_at'])); ?>
                                </td>
                                <td style="text-align: right;">
                                    <strong style="color: var(--c-ink); font-family: 'Bricolage Grotesque', sans-serif;">
                                        <?= format_currency($ord['total_amount']); ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--c-muted); padding: 2rem;">
                                No transactions completed yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
