<?php
/**
 * ScreenBites - Operational Utilities Overhead Ledger
 * Pure procedural module modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Utilities & Operational Overhead';
require_once dirname(__DIR__) . '/includes/header.php';

$selectedYear = (int)($_GET['year'] ?? date('Y'));
$selectedType = trim($_GET['type'] ?? '');

$whereClause = "WHERE billing_year = {$selectedYear}";
if (!empty($selectedType) && in_array($selectedType, ['Electricity', 'Water'], true)) {
    $whereClause .= " AND utility_type = '{$selectedType}'";
}

$sqlExpenses = "SELECT u.*, us.full_name AS recorder_name 
                FROM utility_expenses u
                LEFT JOIN users us ON u.recorded_by = us.user_id
                {$whereClause}
                ORDER BY u.billing_year DESC, u.billing_month DESC, u.utility_type ASC";
$resExpenses = mysqli_query($conn, $sqlExpenses);

// Totals across selected year
$resUtilTotals = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(bill_amount), 0) AS total_utilities,
        COALESCE(SUM(CASE WHEN utility_type = 'Electricity' THEN bill_amount ELSE 0 END), 0) AS total_electricity,
        COALESCE(SUM(CASE WHEN utility_type = 'Water' THEN bill_amount ELSE 0 END), 0) AS total_water
    FROM utility_expenses 
    WHERE billing_year = {$selectedYear}
");
$utilTotals = mysqli_fetch_assoc($resUtilTotals);
$totalUtilities   = (float)$utilTotals['total_utilities'];
$totalElectricity = (float)$utilTotals['total_electricity'];
$totalWater       = (float)$utilTotals['total_water'];

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<div class="page-head">
    <div>
        <h1>Operational Utilities Overhead</h1>
        <p>Log and audit monthly electric and water expenses for Net Operating Profit computation.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= BASE_URL; ?>/utilities/create.php" class="btn btn-primary">+ Log Utility Bill</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Total Utilities (<?= $selectedYear; ?>)</p>
        <p class="kpi-value" style="color: var(--c-red);"><?= format_currency($totalUtilities); ?></p>
        <p class="kpi-note">Electricity + Water overhead</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Electricity Overhead</p>
        <p class="kpi-value" style="color: var(--c-indigo);"><?= format_currency($totalElectricity); ?></p>
        <p class="kpi-note">Projectors, audio amps &amp; central HVAC</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Water Utility Overhead</p>
        <p class="kpi-value" style="color: #0284c7;"><?= format_currency($totalWater); ?></p>
        <p class="kpi-note">Restrooms &amp; concessions washdown</p>
    </div>
</div>

<!-- Utility Expenses Ledger Section -->
<div class="page-head" style="margin-top: 1.5rem; margin-bottom: 0.75rem;">
    <div>
        <h2>Utility Billing Records</h2>
        <p>Audited monthly bills for year <?= $selectedYear; ?>.</p>
    </div>
    
    <!-- Filter Form -->
    <form method="GET" action="<?= BASE_URL; ?>/utilities/index.php" style="display: flex; gap: 0.5rem; align-items: center;">
        <select name="type" class="form-control" style="width: auto; padding: 0.4rem 0.6rem;">
            <option value="">All Utilities</option>
            <option value="Electricity" <?= $selectedType === 'Electricity' ? 'selected' : ''; ?>>Electricity Only</option>
            <option value="Water" <?= $selectedType === 'Water' ? 'selected' : ''; ?>>Water Only</option>
        </select>

        <select name="year" class="form-control" style="width: auto; padding: 0.4rem 0.6rem;">
            <?php for ($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
                <option value="<?= $y; ?>" <?= $selectedYear === $y ? 'selected' : ''; ?>><?= $y; ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
    </form>
</div>

<div class="card">
    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th>Billing Period</th>
                    <th>Type</th>
                    <th>Bill Amount</th>
                    <th>Status</th>
                    <th>Payment / Due Date</th>
                    <th>Recorded By / Notes</th>
                    <th style="text-align: right; width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resExpenses && mysqli_num_rows($resExpenses) > 0): ?>
                    <?php while ($exp = mysqli_fetch_assoc($resExpenses)): 
                        $expId = (int)$exp['expense_id'];
                        $isPaid = $exp['payment_status'] === 'Paid';
                        $monthLabel = ($monthNames[(int)$exp['billing_month']] ?? 'Month ' . $exp['billing_month']) . ' ' . $exp['billing_year'];
                    ?>
                        <tr>
                            <td>
                                <strong><?= $monthLabel; ?></strong>
                            </td>
                            <td>
                                <?php if ($exp['utility_type'] === 'Electricity'): ?>
                                    <span style="display: inline-block; padding: 0.2rem 0.5rem; font-size: 0.8rem; border-radius: 4px; background: rgba(43, 45, 122, 0.12); color: var(--c-indigo); border: 1px solid rgba(43, 45, 122, 0.25); font-weight: 700;">
                                        ⚡ Electricity
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 0.2rem 0.5rem; font-size: 0.8rem; border-radius: 4px; background: rgba(2, 132, 199, 0.12); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.25); font-weight: 700;">
                                        💧 Water
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--c-ink); font-size: 1rem; font-family: 'Bricolage Grotesque', sans-serif;">
                                    <?= format_currency($exp['bill_amount']); ?>
                                </strong>
                            </td>
                            <td>
                                <span style="display: inline-block; padding: 0.2rem 0.5rem; font-size: 0.8rem; border-radius: 4px; <?= $isPaid ? 'background: #e7f6ec; color: #1b5e35; border: 1px solid #a9dbb8;' : 'background: #fdeaec; color: #8f1f2c; border: 1px solid #f3b7be;'; ?> font-weight: 600;">
                                    <?= sanitize($exp['payment_status']); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--c-muted);">
                                <?php if ($isPaid && !empty($exp['payment_date'])): ?>
                                    Paid: <?= date('M d, Y', strtotime($exp['payment_date'])); ?>
                                <?php elseif (!empty($exp['due_date'])): ?>
                                    Due: <?= date('M d, Y', strtotime($exp['due_date'])); ?>
                                <?php else: ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; color: var(--c-ink);"><?= sanitize($exp['notes'] ?? 'No notes recorded'); ?></div>
                                <div style="font-size: 0.75rem; color: var(--c-muted); margin-top: 0.2rem;">By: <?= sanitize($exp['recorder_name'] ?? 'Admin'); ?></div>
                            </td>
                            <td style="text-align: right;">
                                <a href="<?= BASE_URL; ?>/utilities/edit.php?id=<?= $expId; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                <a href="<?= BASE_URL; ?>/utilities/delete.php?id=<?= $expId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this utility expense bill?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--c-muted); padding: 2.5rem;">
                            No utility bills found for year <?= $selectedYear; ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
