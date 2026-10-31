<?php
/**
 * ScreenBites - Net Income & P&L Statement Report
 * Procedural reporting module modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Net Income Report';
require_once dirname(__DIR__) . '/includes/header.php';

$selectedYear  = (int)($_GET['year'] ?? date('Y'));
$selectedMonth = (int)($_GET['month'] ?? date('n'));

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// 1. Gross Revenue
$revRes = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(subtotal_amount + tax_amount), 0) AS gross_revenue,
        COALESCE(SUM(subtotal_amount), 0) AS subtotal,
        COALESCE(SUM(tax_amount), 0) AS tax
    FROM orders 
    WHERE MONTH(created_at) = {$selectedMonth} 
      AND YEAR(created_at) = {$selectedYear} 
      AND order_status = 'Completed'
");
$revRow = mysqli_fetch_assoc($revRes);
$grossRevenue = (float)$revRow['gross_revenue'];

// Ticket Revenue
$tRes = mysqli_query($conn, "
    SELECT COALESCE(SUM(t.price_charged), 0) AS ticket_revenue
    FROM tickets t
    INNER JOIN orders o ON t.order_id = o.order_id
    WHERE MONTH(o.created_at) = {$selectedMonth}
      AND YEAR(o.created_at) = {$selectedYear}
      AND o.order_status = 'Completed'
      AND t.ticket_status = 'Valid'
");
$tRow = mysqli_fetch_assoc($tRes);
$ticketRevenue = (float)$tRow['ticket_revenue'];

// Concessions Revenue (Individual snacks + Value Combos)
$cRes = mysqli_query($conn, "
    SELECT COALESCE(SUM(oi.quantity * oi.unit_price_charged), 0) AS conc_revenue
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.order_id
    WHERE MONTH(o.created_at) = {$selectedMonth}
      AND YEAR(o.created_at) = {$selectedYear}
      AND o.order_status = 'Completed'
");
$cRow = mysqli_fetch_assoc($cRes);
$snackRevenue = (float)$cRow['conc_revenue'];

$cbRes = mysqli_query($conn, "
    SELECT COALESCE(SUM(oc.quantity * oc.unit_price_charged), 0) AS combo_revenue
    FROM order_combos oc
    INNER JOIN orders o ON oc.order_id = o.order_id
    WHERE MONTH(o.created_at) = {$selectedMonth}
      AND YEAR(o.created_at) = {$selectedYear}
      AND o.order_status = 'Completed'
");
$cbRow = mysqli_fetch_assoc($cbRes);
$comboRevenue = (float)$cbRow['combo_revenue'];

$concRevenue = $snackRevenue + $comboRevenue;

// 2. Cost of Goods Sold (COGS: Individual products + Combo constituent items)
$cogsRes = mysqli_query($conn, "
    SELECT COALESCE(SUM(oi.quantity * p.unit_cost), 0) AS total_cogs
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.product_id
    INNER JOIN orders o ON oi.order_id = o.order_id
    WHERE MONTH(o.created_at) = {$selectedMonth}
      AND YEAR(o.created_at) = {$selectedYear}
      AND o.order_status = 'Completed'
");
$cogsRow = mysqli_fetch_assoc($cogsRes);
$snackCogs = (float)$cogsRow['total_cogs'];

$comboCogsRes = mysqli_query($conn, "
    SELECT COALESCE(SUM(oc.quantity * ci.quantity * p.unit_cost), 0) AS combo_cogs
    FROM order_combos oc
    INNER JOIN combo_items ci ON oc.combo_id = ci.combo_id
    INNER JOIN products p ON ci.product_id = p.product_id
    INNER JOIN orders o ON oc.order_id = o.order_id
    WHERE MONTH(o.created_at) = {$selectedMonth}
      AND YEAR(o.created_at) = {$selectedYear}
      AND o.order_status = 'Completed'
");
$comboCogsRow = mysqli_fetch_assoc($comboCogsRes);
$comboCogs = (float)$comboCogsRow['combo_cogs'];

$cogs = $snackCogs + $comboCogs;

// Gross Profit
$grossProfit = $grossRevenue - $cogs;

// 3. Operational Utilities Overhead
$utilRes = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(bill_amount), 0) AS total_utilities,
        COALESCE(SUM(CASE WHEN utility_type = 'Electricity' THEN bill_amount ELSE 0 END), 0) AS electricity,
        COALESCE(SUM(CASE WHEN utility_type = 'Water' THEN bill_amount ELSE 0 END), 0) AS water
    FROM utility_expenses
    WHERE billing_month = {$selectedMonth}
      AND billing_year = {$selectedYear}
");
$utilRow = mysqli_fetch_assoc($utilRes);
$totalUtilities = (float)$utilRow['total_utilities'];
$elecExpense    = (float)$utilRow['electricity'];
$waterExpense   = (float)$utilRow['water'];

// Total Operational Expenses & Net Operating Income
$totalExpenses = $cogs + $totalUtilities;
$netIncome     = $grossRevenue - $totalExpenses;
$profitMargin  = $grossRevenue > 0 ? round(($netIncome / $grossRevenue) * 100, 1) : 0;
?>

<div class="page-head">
    <div>
        <h1>Net Income &amp; Operating Margin Report</h1>
        <p>Profit &amp; Loss breakdown for <?= $monthNames[$selectedMonth] . ' ' . $selectedYear; ?>.</p>
    </div>
    
    <!-- Filter form -->
    <form method="GET" action="<?= BASE_URL; ?>/reports/financial.php" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
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
        <button type="submit" class="btn btn-sm btn-primary">Generate P&amp;L</button>
    </form>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Gross Revenue</p>
        <p class="kpi-value" style="color: var(--c-indigo);"><?= format_currency($grossRevenue); ?></p>
        <p class="kpi-note">Box office &amp; concessions sales</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Cost of Goods Sold (COGS)</p>
        <p class="kpi-value" style="color: var(--c-muted);"><?= format_currency($cogs); ?></p>
        <p class="kpi-note">Snack inventory wholesale cost</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Utilities Overhead</p>
        <p class="kpi-value" style="color: var(--c-red);"><?= format_currency($totalUtilities); ?></p>
        <p class="kpi-note">Electricity: <?= format_currency($elecExpense); ?> &bull; Water: <?= format_currency($waterExpense); ?></p>
    </div>

    <div class="kpi" style="border-top: 4px solid <?= $netIncome >= 0 ? '#2f9e55' : 'var(--c-red)'; ?>;">
        <p class="kpi-label">Net Operating Income</p>
        <p class="kpi-value" style="color: <?= $netIncome >= 0 ? '#1b5e35' : 'var(--c-red)'; ?>;">
            <?= format_currency($netIncome); ?>
        </p>
        <p class="kpi-note">Profit Margin: <strong><?= $profitMargin; ?>%</strong></p>
    </div>
</div>

<!-- Formal Income Statement Table (P&L) -->
<div class="card" style="margin-top: 1.5rem;">
    <div style="padding: 1.25rem 1.25rem 0.5rem;">
        <h3 style="margin: 0; font-size: 1.15rem; color: var(--c-ink);">Profit &amp; Loss Statement</h3>
        <p style="margin: 0.2rem 0 1rem; color: var(--c-muted); font-size: 0.85rem;">Standard financial accounting statement for <?= $monthNames[$selectedMonth] . ' ' . $selectedYear; ?>.</p>
    </div>

    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th>Line Item Description</th>
                    <th>Category</th>
                    <th style="text-align: right; width: 180px;">Amount (PHP)</th>
                </tr>
            </thead>
            <tbody>
                <!-- Revenue -->
                <tr style="background: #fafbfe;">
                    <td colspan="3"><strong style="color: var(--c-indigo); font-size: 0.95rem;">1. OPERATING REVENUE</strong></td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem;">Box Office Ticket Sales</td>
                    <td>Cinema Admissions</td>
                    <td style="text-align: right; font-weight: 600;"><?= format_currency($ticketRevenue); ?></td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem;">Snack Bar Concessions Sales</td>
                    <td>Food &amp; Beverage</td>
                    <td style="text-align: right; font-weight: 600;"><?= format_currency($concRevenue); ?></td>
                </tr>
                <tr style="border-top: 1px solid var(--c-line); font-weight: 700;">
                    <td style="padding-left: 2rem;">TOTAL GROSS REVENUE</td>
                    <td>&mdash;</td>
                    <td style="text-align: right; color: var(--c-indigo);"><?= format_currency($grossRevenue); ?></td>
                </tr>

                <!-- COGS -->
                <tr style="background: #fafbfe;">
                    <td colspan="3"><strong style="color: var(--c-ink); font-size: 0.95rem;">2. COST OF GOODS SOLD</strong></td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem;">Concessions Wholesale Inventory Cost</td>
                    <td>Direct Product Cost</td>
                    <td style="text-align: right; color: var(--c-red);">&minus; <?= format_currency($cogs); ?></td>
                </tr>
                <tr style="border-top: 1px solid var(--c-line); font-weight: 700;">
                    <td style="padding-left: 2rem;">GROSS OPERATING PROFIT</td>
                    <td>&mdash;</td>
                    <td style="text-align: right;"><?= format_currency($grossProfit); ?></td>
                </tr>

                <!-- Utilities Operating Overhead -->
                <tr style="background: #fafbfe;">
                    <td colspan="3"><strong style="color: var(--c-red); font-size: 0.95rem;">3. OPERATING OVERHEAD (UTILITIES)</strong></td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem;">⚡ Electricity (Projectors, HVAC, Audio)</td>
                    <td>Electric Draw</td>
                    <td style="text-align: right; color: var(--c-red);">&minus; <?= format_currency($elecExpense); ?></td>
                </tr>
                <tr>
                    <td style="padding-left: 2rem;">💧 Water (Restrooms &amp; Concessions)</td>
                    <td>Water Utility</td>
                    <td style="text-align: right; color: var(--c-red);">&minus; <?= format_currency($waterExpense); ?></td>
                </tr>
                <tr style="border-top: 1px solid var(--c-line); font-weight: 700;">
                    <td style="padding-left: 2rem;">TOTAL OPERATING EXPENSES</td>
                    <td>&mdash;</td>
                    <td style="text-align: right; color: var(--c-red);">&minus; <?= format_currency($totalExpenses); ?></td>
                </tr>

                <!-- Net Income Bottom Line -->
                <tr style="background: <?= $netIncome >= 0 ? '#f0fdf4' : '#fef2f2'; ?>; border-top: 2px solid <?= $netIncome >= 0 ? '#2f9e55' : 'var(--c-red)'; ?>; font-size: 1.1rem; font-weight: 800;">
                    <td style="padding-left: 2rem; color: <?= $netIncome >= 0 ? '#1b5e35' : '#8f1f2c'; ?>;">
                        NET OPERATING INCOME
                    </td>
                    <td style="font-size: 0.9rem; font-weight: 600; color: var(--c-muted);">
                        Margin: <?= $profitMargin; ?>%
                    </td>
                    <td style="text-align: right; color: <?= $netIncome >= 0 ? '#1b5e35' : '#8f1f2c'; ?>; font-family: 'Bricolage Grotesque', sans-serif;">
                        <?= format_currency($netIncome); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
