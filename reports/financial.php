<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$activeTab = $_GET['tab'] ?? 'pnl';
$pageTitle = ($activeTab === 'inventory') ? 'Inventory Valuation Report' : 'Net Income & P&L Report';
require_once dirname(__DIR__) . '/includes/header.php';

$selectedYear  = (int)($_GET['year'] ?? date('Y'));
$selectedMonth = (int)($_GET['month'] ?? date('n'));

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

if ($activeTab === 'inventory') {
    $catFilter = trim($_GET['category'] ?? '');
    $whereProd = "WHERE is_active = 1";
    if (!empty($catFilter)) {
        $catSafe = mysqli_real_escape_string($conn, $catFilter);
        $whereProd .= " AND category = '{$catSafe}'";
    }

    $invSql = "SELECT product_id, product_name, category, unit_cost, retail_price, current_stock, reorder_level,
                      (current_stock * unit_cost) AS total_cost_value,
                      (current_stock * retail_price) AS total_retail_value,
                      ((retail_price - unit_cost) * current_stock) AS potential_profit
               FROM products
               {$whereProd}
               ORDER BY category ASC, product_name ASC";
    $invRes = mysqli_query($conn, $invSql);

    $inventoryItems = [];
    $totalStockUnits = 0;
    $totalAssetValuation = 0.0;
    $totalPotentialRetail = 0.0;
    $lowStockCount = 0;

    if ($invRes) {
        while ($row = mysqli_fetch_assoc($invRes)) {
            $inventoryItems[] = $row;
            $stock = (int)$row['current_stock'];
            $totalStockUnits += $stock;
            $totalAssetValuation += (float)$row['total_cost_value'];
            $totalPotentialRetail += (float)$row['total_retail_value'];
            if ($stock <= (int)$row['reorder_level']) {
                $lowStockCount++;
            }
        }
    }

    $replenishSql = "SELECT SUM(quantity_added * purchase_cost) AS total_restock_spend,
                            SUM(quantity_added) AS total_units_received
                     FROM stock_restocks
                     WHERE MONTH(created_at) = {$selectedMonth} AND YEAR(created_at) = {$selectedYear}";
    $replenishRes = mysqli_query($conn, $replenishSql);
    $replenishData = mysqli_fetch_assoc($replenishRes) ?: ['total_restock_spend' => 0, 'total_units_received' => 0];

    $catListRes = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category ASC");

} else {
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
    $grossProfit = $grossRevenue - $cogs;

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

    $totalExpenses = $cogs + $totalUtilities;
    $netIncome     = $grossRevenue - $totalExpenses;
    $profitMargin  = $grossRevenue > 0 ? round(($netIncome / $grossRevenue) * 100, 1) : 0;
}
?>

<div class="sb-page-header">
    <div>
        <h1>Financial &amp; Stock Reports</h1>
        <p>Comprehensive profit &amp; loss analysis, cost of goods sold, and inventory asset valuation.</p>
    </div>

    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="<?= BASE_URL; ?>/reports/financial.php?tab=pnl&month=<?= $selectedMonth; ?>&year=<?= $selectedYear; ?>"
           class="btn <?= $activeTab === 'pnl' ? 'btn-primary' : 'btn-secondary'; ?>">
            📊 Income Statement (P&amp;L)
        </a>
        <a href="<?= BASE_URL; ?>/reports/financial.php?tab=inventory&month=<?= $selectedMonth; ?>&year=<?= $selectedYear; ?>"
           class="btn <?= $activeTab === 'inventory' ? 'btn-primary' : 'btn-secondary'; ?>">
            📦 Inventory Asset Valuation
        </a>
        <a href="<?= BASE_URL; ?>/concessions/restock_history.php" class="btn btn-secondary" style="border: 1px solid rgba(16, 185, 129, 0.4); color: #10b981;">
            📋 Restock Log
        </a>
    </div>
</div>

<?php if ($activeTab === 'pnl'): ?>

    <div class="sb-card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
        <form method="GET" action="<?= BASE_URL; ?>/reports/financial.php" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="pnl">
            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">Period:</span>
            <select name="month" style="padding: 0.4rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit;">
                <?php foreach ($monthNames as $mNum => $mName): ?>
                    <option value="<?= $mNum; ?>" <?= $mNum === $selectedMonth ? 'selected' : ''; ?>><?= $mName; ?></option>
                <?php endforeach; ?>
            </select>

            <select name="year" style="padding: 0.4rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit;">
                <?php for ($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
                    <option value="<?= $y; ?>" <?= $y === $selectedYear ? 'selected' : ''; ?>><?= $y; ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1rem;">Generate Statement</button>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="sb-card" style="border-left: 4px solid #6366f1;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Gross Revenue</div>
            <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: #818cf8;"><?= format_currency($grossRevenue); ?></div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Box office &amp; concessions sales</div>
        </div>

        <div class="sb-card" style="border-left: 4px solid #f59e0b;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Cost of Goods Sold (COGS)</div>
            <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: #f59e0b;"><?= format_currency($cogs); ?></div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Wholesale cost of snacks sold</div>
        </div>

        <div class="sb-card" style="border-left: 4px solid #ef4444;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Utilities Overhead</div>
            <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: #ef4444;"><?= format_currency($totalUtilities); ?></div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Power: <?= format_currency($elecExpense); ?> &bull; Water: <?= format_currency($waterExpense); ?></div>
        </div>

        <div class="sb-card" style="border-left: 4px solid <?= $netIncome >= 0 ? '#10b981' : '#ef4444'; ?>;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Net Operating Income</div>
            <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: <?= $netIncome >= 0 ? '#10b981' : '#ef4444'; ?>;">
                <?= format_currency($netIncome); ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Profit Margin: <strong><?= $profitMargin; ?>%</strong></div>
        </div>
    </div>

    <div class="sb-card" style="padding: 0; overflow-x: auto;">
        <div style="padding: 1.25rem 1.25rem 0.5rem;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;">Profit &amp; Loss Statement</h3>
            <p style="margin: 0.2rem 0 0.5rem; color: var(--text-muted); font-size: 0.85rem;">Accounting period: <?= $monthNames[$selectedMonth] . ' ' . $selectedYear; ?>.</p>
        </div>

        <table class="sb-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: rgba(0,0,0,0.2); text-align: left; border-bottom: 1px solid var(--card-border);">
                    <th style="padding: 0.75rem 1.25rem;">Line Item Description</th>
                    <th style="padding: 0.75rem 1rem;">Category</th>
                    <th style="padding: 0.75rem 1.25rem; text-align: right; width: 200px;">Amount (PHP)</th>
                </tr>
            </thead>
            <tbody>

                <tr style="background: rgba(255,255,255,0.02);">
                    <td colspan="3" style="padding: 0.75rem 1.25rem;"><strong style="color: #818cf8; font-size: 0.95rem;">1. OPERATING REVENUE</strong></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--card-border);">
                    <td style="padding: 0.65rem 1.25rem 0.65rem 2rem;">Box Office Ticket Sales</td>
                    <td style="padding: 0.65rem 1rem; color: var(--text-muted);">Cinema Admissions</td>
                    <td style="padding: 0.65rem 1.25rem; text-align: right; font-weight: 600;"><?= format_currency($ticketRevenue); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--card-border);">
                    <td style="padding: 0.65rem 1.25rem 0.65rem 2rem;">Snack Bar Concessions Sales</td>
                    <td style="padding: 0.65rem 1rem; color: var(--text-muted);">Food &amp; Beverage</td>
                    <td style="padding: 0.65rem 1.25rem; text-align: right; font-weight: 600;"><?= format_currency($concRevenue); ?></td>
                </tr>
                <tr style="border-bottom: 2px solid var(--card-border); font-weight: 700; background: rgba(99, 102, 241, 0.05);">
                    <td style="padding: 0.75rem 1.25rem 0.75rem 2rem;">TOTAL GROSS REVENUE</td>
                    <td style="padding: 0.75rem 1rem;">&mdash;</td>
                    <td style="padding: 0.75rem 1.25rem; text-align: right; color: #818cf8;"><?= format_currency($grossRevenue); ?></td>
                </tr>

                <tr style="background: rgba(255,255,255,0.02);">
                    <td colspan="3" style="padding: 0.75rem 1.25rem;"><strong style="color: #f59e0b; font-size: 0.95rem;">2. COST OF GOODS SOLD</strong></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--card-border);">
                    <td style="padding: 0.65rem 1.25rem 0.65rem 2rem;">Concessions Wholesale Inventory Cost (Direct Stock Cost)</td>
                    <td style="padding: 0.65rem 1rem; color: var(--text-muted);">Direct Product COGS</td>
                    <td style="padding: 0.65rem 1.25rem; text-align: right; color: #f59e0b;">&minus; <?= format_currency($cogs); ?></td>
                </tr>
                <tr style="border-bottom: 2px solid var(--card-border); font-weight: 700; background: rgba(245, 158, 11, 0.05);">
                    <td style="padding: 0.75rem 1.25rem 0.75rem 2rem;">GROSS OPERATING PROFIT</td>
                    <td style="padding: 0.75rem 1rem;">&mdash;</td>
                    <td style="padding: 0.75rem 1.25rem; text-align: right;"><?= format_currency($grossProfit); ?></td>
                </tr>

                <tr style="background: rgba(255,255,255,0.02);">
                    <td colspan="3" style="padding: 0.75rem 1.25rem;"><strong style="color: #ef4444; font-size: 0.95rem;">3. OPERATING OVERHEAD (UTILITIES)</strong></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--card-border);">
                    <td style="padding: 0.65rem 1.25rem 0.65rem 2rem;">⚡ Electricity (Projectors, HVAC, Audio)</td>
                    <td style="padding: 0.65rem 1rem; color: var(--text-muted);">Electric Draw</td>
                    <td style="padding: 0.65rem 1.25rem; text-align: right; color: #ef4444;">&minus; <?= format_currency($elecExpense); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--card-border);">
                    <td style="padding: 0.65rem 1.25rem 0.65rem 2rem;">💧 Water (Restrooms &amp; Concessions)</td>
                    <td style="padding: 0.65rem 1rem; color: var(--text-muted);">Water Utility</td>
                    <td style="padding: 0.65rem 1.25rem; text-align: right; color: #ef4444;">&minus; <?= format_currency($waterExpense); ?></td>
                </tr>
                <tr style="border-bottom: 2px solid var(--card-border); font-weight: 700;">
                    <td style="padding: 0.75rem 1.25rem 0.75rem 2rem;">TOTAL OPERATING EXPENSES</td>
                    <td style="padding: 0.75rem 1rem;">&mdash;</td>
                    <td style="padding: 0.75rem 1.25rem; text-align: right; color: #ef4444;">&minus; <?= format_currency($totalExpenses); ?></td>
                </tr>

                <tr style="background: <?= $netIncome >= 0 ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; font-size: 1.1rem; font-weight: 800;">
                    <td style="padding: 1rem 1.25rem 1rem 2rem; color: <?= $netIncome >= 0 ? '#10b981' : '#ef4444'; ?>;">
                        NET OPERATING INCOME
                    </td>
                    <td style="padding: 1rem 1rem; font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">
                        Margin: <?= $profitMargin; ?>%
                    </td>
                    <td style="padding: 1rem 1.25rem; text-align: right; color: <?= $netIncome >= 0 ? '#10b981' : '#ef4444'; ?>;">
                        <?= format_currency($netIncome); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

<?php else: ?>

    <div class="sb-card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
        <form method="GET" action="<?= BASE_URL; ?>/reports/financial.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="inventory">
            <div>
                <label style="display: block; font-size: 0.85rem; margin-bottom: 0.35rem; color: var(--text-muted); font-weight: 600;">Category</label>
                <select name="category" style="padding: 0.45rem 0.75rem; border-radius: 6px; border: 1px solid var(--card-border); background: rgba(0,0,0,0.2); color: inherit;">
                    <option value="">-- All Categories --</option>
                    <?php while ($cat = mysqli_fetch_assoc($catListRes)): ?>
                        <option value="<?= sanitize($cat['category']); ?>" <?= ($catFilter === $cat['category']) ? 'selected' : ''; ?>>
                            <?= sanitize($cat['category']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.25rem;">Filter Valuation</button>
                <?php if (!empty($catFilter)): ?>
                    <a href="<?= BASE_URL; ?>/reports/financial.php?tab=inventory" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="sb-card" style="border-left: 4px solid var(--accent-gold);">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Total Asset Valuation (Cost)</div>
            <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: var(--accent-gold);">
                <?= format_currency($totalAssetValuation); ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Total capital tied up in stock on hand</div>
        </div>

        <div class="sb-card" style="border-left: 4px solid #3b82f6;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Replenishment Spend (This Month)</div>
            <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: #60a5fa;">
                <?= format_currency($replenishData['total_restock_spend']); ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;"><?= number_format((int)$replenishData['total_units_received']); ?> units restocked in <?= $monthNames[$selectedMonth]; ?></div>
        </div>

        <div class="sb-card" style="border-left: 4px solid #10b981;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Potential Retail Realization</div>
            <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: #10b981;">
                <?= format_currency($totalPotentialRetail); ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Projected revenue if all stock is sold</div>
        </div>

        <div class="sb-card" style="border-left: 4px solid #6366f1;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">On-Hand Stock Units</div>
            <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: #818cf8;">
                <?= number_format($totalStockUnits); ?> units
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                <?= count($inventoryItems); ?> active product lines
            </div>
        </div>

        <div class="sb-card" style="border-left: 4px solid <?= $lowStockCount > 0 ? '#ef4444' : '#10b981'; ?>;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Stock Attention Needed</div>
            <div style="font-size: 1.8rem; font-weight: 700; margin-top: 0.25rem; color: <?= $lowStockCount > 0 ? '#ef4444' : '#10b981'; ?>;">
                <?= $lowStockCount; ?> items
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">At or below reorder threshold</div>
        </div>
    </div>

    <div class="sb-card" style="padding: 0; overflow-x: auto;">
        <div style="padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;">Concession Inventory Valuation Schedule</h3>
                <p style="margin: 0.2rem 0 0; color: var(--text-muted); font-size: 0.85rem;">Calculated asset values: On-Hand Stock &times; Unit Acquisition Cost.</p>
            </div>
            <a href="<?= BASE_URL; ?>/concessions/restock.php" class="btn btn-sm btn-primary">
                + Restock Any Item
            </a>
        </div>

        <table class="sb-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: rgba(0,0,0,0.2); text-align: left; border-bottom: 1px solid var(--card-border);">
                    <th style="padding: 0.75rem 1rem;">Product Item</th>
                    <th style="padding: 0.75rem 1rem;">Category</th>
                    <th style="padding: 0.75rem 1rem; text-align: center;">Stock on Hand</th>
                    <th style="padding: 0.75rem 1rem; text-align: right;">Unit Cost</th>
                    <th style="padding: 0.75rem 1rem; text-align: right;">Retail Price</th>
                    <th style="padding: 0.75rem 1rem; text-align: right;">Total Cost Valuation</th>
                    <th style="padding: 0.75rem 1rem; text-align: right;">Potential Revenue</th>
                    <th style="padding: 0.75rem 1rem; text-align: center;">Margin (%)</th>
                    <th style="padding: 0.75rem 1rem; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($inventoryItems)): ?>
                    <?php foreach ($inventoryItems as $item):
                        $isLow = (int)$item['current_stock'] <= (int)$item['reorder_level'];
                        $marginPct = (float)$item['retail_price'] > 0
                            ? round((((float)$item['retail_price'] - (float)$item['unit_cost']) / (float)$item['retail_price']) * 100, 1)
                            : 0;
                    ?>
                        <tr style="border-bottom: 1px solid var(--card-border);">
                            <td style="padding: 0.75rem 1rem; font-weight: 600;">
                                <?= sanitize($item['product_name']); ?>
                                <?php if ($isLow): ?>
                                    <span style="font-size: 0.75rem; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.15rem 0.4rem; border-radius: 4px; margin-left: 0.5rem; font-weight: normal;">
                                        Low Stock (&le; <?= (int)$item['reorder_level']; ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; color: var(--text-muted);">
                                <?= sanitize($item['category']); ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 700; color: <?= $isLow ? '#ef4444' : 'inherit'; ?>;">
                                <?= number_format((int)$item['current_stock']); ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace;">
                                <?= format_currency($item['unit_cost']); ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace;">
                                <?= format_currency($item['retail_price']); ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: var(--accent-gold);">
                                <?= format_currency($item['total_cost_value']); ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 600; color: #10b981;">
                                <?= format_currency($item['total_retail_value']); ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
                                <?= $marginPct; ?>%
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center;">
                                <a href="<?= BASE_URL; ?>/concessions/restock.php?product_id=<?= (int)$item['product_id']; ?>"
                                   class="btn btn-sm btn-secondary"
                                   style="font-size: 0.8rem; padding: 0.25rem 0.6rem; border: 1px solid rgba(16, 185, 129, 0.4); color: #10b981;">
                                    + Restock
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background: rgba(255,255,255,0.03); font-weight: 800; border-top: 2px solid var(--card-border);">
                        <td style="padding: 1rem;" colspan="2">TOTAL INVENTORY ASSET SUMMARY</td>
                        <td style="padding: 1rem; text-align: center;"><?= number_format($totalStockUnits); ?></td>
                        <td style="padding: 1rem;" colspan="2"></td>
                        <td style="padding: 1rem; text-align: right; color: var(--accent-gold); font-size: 1.05rem;">
                            <?= format_currency($totalAssetValuation); ?>
                        </td>
                        <td style="padding: 1rem; text-align: right; color: #10b981; font-size: 1.05rem;">
                            <?= format_currency($totalPotentialRetail); ?>
                        </td>
                        <td style="padding: 1rem;" colspan="2"></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="padding: 2.5rem 1rem; text-align: center; color: var(--text-muted);">
                            No concession products found matching category filter.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
