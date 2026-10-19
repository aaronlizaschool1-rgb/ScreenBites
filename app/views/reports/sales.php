<?php
/**
 * app/views/reports/sales.php  (FR14)
 * ASSUMPTION: receives $financial, $selectedYear, $selectedMonth like financial.php.
 * Confirm the real variables with Aaron (ReportController).
 */


$data = is_array($financial ?? null) ? $financial : (is_array($sales ?? null) ? $sales : []);
$val  = fn(string $key): float => (float) ($data[$key] ?? 0);

$money = function ($amount): string {
    return '&#8369;' . number_format((float) $amount, 2);
};
$share = fn(float $part, float $whole): float => $whole > 0 ? round($part / $whole * 100, 1) : 0.0;

$selectedYear  = (int) ($selectedYear ?? date('Y'));
$selectedMonth = (int) ($selectedMonth ?? date('n'));
$monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
               7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
$years = range((int) date('Y') - 3, (int) date('Y') + 1);
if (!in_array($selectedYear, $years, true)) { $years[] = $selectedYear; sort($years); }
$period = $data['period'] ?? (($monthNames[$selectedMonth] ?? '') . ' ' . $selectedYear);

$ticketRev  = $val('ticket_revenue');
$concRev    = $val('concession_revenue');
$total      = $ticketRev + $concRev;
$ticketsQty = (int) ($data['tickets_sold'] ?? 0);
$itemsQty   = (int) ($data['items_sold'] ?? 0);
$avgTicket  = $ticketsQty > 0 ? $ticketRev / $ticketsQty : 0;
$avgItem    = $itemsQty > 0 ? $concRev / $itemsQty : 0;

$lines = [
    ['Box office', $ticketRev, $ticketsQty . ' tickets', $avgTicket, 'per ticket'],
    ['Concessions', $concRev, $itemsQty . ' items', $avgItem, 'per item'],
];
$chartData = ['revenueSplit' => ['ticket' => $ticketRev, 'concession' => $concRev]];
?>
<div class="page-head">
    <div>
        <h1>Sales report</h1>
        <p>Box office compared with concessions for <?= htmlspecialchars((string) $period); ?>.</p>
    </div>
</div>

<form class="report-filters" method="GET" action="<?= BASE_URL; ?>/reports/sales">
    <div class="field">
        <label for="month">Month</label>
        <select id="month" name="month">
            <?php foreach ($monthNames as $num => $name): ?>
                <option value="<?= $num; ?>" <?= $num === $selectedMonth ? 'selected' : ''; ?>><?= $name; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="year">Year</label>
        <select id="year" name="year">
            <?php foreach ($years as $y): ?>
                <option value="<?= $y; ?>" <?= $y === $selectedYear ? 'selected' : ''; ?>><?= $y; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn">Show report</button>
</form>

<?php if (!$data): ?>
    <div class="card"><p class="empty-note">No sales data was passed to this page.</p></div>
<?php else: ?>
<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Total sales</p>
        <p class="kpi-value"><?= $money($total); ?></p>
        <p class="kpi-note">Before expenses</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Box office</p>
        <p class="kpi-value"><?= $money($ticketRev); ?></p>
        <p class="kpi-note"><?= number_format($ticketsQty); ?> tickets sold</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Concessions</p>
        <p class="kpi-value"><?= $money($concRev); ?></p>
        <p class="kpi-note"><?= number_format($itemsQty); ?> items sold</p>
    </div>
</div>

<div class="report-grid">
    <div class="card">
        <h2 class="card-title">Sales breakdown</h2>
        <div class="table-scroll">
            <table class="ledger">
                <thead>
                    <tr>
                        <th scope="col">Channel</th>
                        <th scope="col" class="num">Revenue</th>
                        <th scope="col" class="num">Average</th>
                        <th scope="col">Share of sales</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lines as [$label, $rev, $qty, $avg, $unit]): $pct = $share($rev, $total); ?>
                    <tr>
                        <th scope="row"><?= $label; ?><small><?= htmlspecialchars($qty); ?></small></th>
                        <td class="num"><?= $money($rev); ?></td>
                        <td class="num"><?= $money($avg); ?><small><?= $unit; ?></small></td>
                        <td>
                            <div class="meter">
                                <div class="meter-bar"><span style="width: <?= $pct; ?>%"></span></div>
                                <span class="meter-pct"><?= number_format($pct, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total">
                        <th scope="row">Total</th>
                        <td class="num"><?= $money($total); ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Ticket vs concessions</h2>
        <div class="chart-box"><canvas id="revenueSplitChart" role="img" aria-label="Doughnut chart of box office versus concession revenue"></canvas></div>
    </div>
</div>

<script>window.ScreenBitesCharts = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL; ?>/assets/js/chart-analytics.js"></script>
<?php endif; ?>