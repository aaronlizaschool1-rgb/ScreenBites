<?php

$fin = is_array($financial ?? null) ? $financial : [];
$val = fn(string $key): float => (float) ($fin[$key] ?? 0);

$money = function ($amount): string {
    $amount = (float) $amount;
    return ($amount < 0 ? '-' : '') . '&#8369;' . number_format(abs($amount), 2);
};

$selectedYear  = (int) ($selectedYear ?? date('Y'));
$selectedMonth = (int) ($selectedMonth ?? date('n'));
$monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
               7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
$years = range((int) date('Y') - 3, (int) date('Y') + 1);
if (!in_array($selectedYear, $years, true)) { $years[] = $selectedYear; sort($years); }

$period   = $fin['period'] ?? (($monthNames[$selectedMonth] ?? '') . ' ' . $selectedYear);
$gross    = $val('gross_revenue');
$expenses = $val('total_expenses');
$net      = $val('net_operating_income');
$marginRaw = $fin['profit_margin'] ?? 0;
$margin    = is_numeric($marginRaw) ? number_format((float) $marginRaw, 1) . '%' : (string) $marginRaw;
$netClass  = $net >= 0 ? 'is-positive' : 'is-negative';
$marginClass = ((float) $marginRaw) >= 0 ? 'is-positive' : 'is-negative';
$hasData   = ($gross != 0 || $expenses != 0);

$chartData = [
    'finance'      => ['gross' => $gross, 'cogs' => $val('cogs'), 'utilities' => $val('total_utilities'), 'net' => $net],
    'revenueSplit' => ['ticket' => $val('ticket_revenue'), 'concession' => $val('concession_revenue')],
];
?>
<div class="page-head">
    <div>
        <h1>Net income report</h1>
        <p>Revenue, costs and net operating income for <?= htmlspecialchars((string) $period); ?>.</p>
    </div>
</div>

<form class="report-filters" method="GET" action="<?= BASE_URL; ?>/reports/financial">
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

<div class="kpi-grid">
    <div class="kpi">
        <p class="kpi-label">Gross revenue</p>
        <p class="kpi-value"><?= $money($gross); ?></p>
        <p class="kpi-note">Box office plus concessions</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Total expenses</p>
        <p class="kpi-value"><?= $money($expenses); ?></p>
        <p class="kpi-note">Cost of goods plus utilities</p>
    </div>
    <div class="kpi is-key">
        <p class="kpi-label">Net operating income</p>
        <p class="kpi-value <?= $netClass; ?>"><?= $money($net); ?></p>
        <p class="kpi-note">Gross revenue minus expenses</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Profit margin</p>
        <p class="kpi-value <?= $marginClass; ?>"><?= htmlspecialchars($margin); ?></p>
        <p class="kpi-note">Net income as a share of revenue</p>
    </div>
</div>

<?php if (!$hasData): ?>
    <div class="card">
        <p class="empty-note">No sales or expenses are recorded for <?= htmlspecialchars((string) $period); ?>. Try another month.</p>
    </div>
<?php else: ?>
<div class="report-grid">
    <div class="card">
        <h2 class="card-title">Income statement</h2>
        <div class="table-scroll">
            <table class="ledger">
                <tbody>
                    <tr class="group"><th colspan="2" scope="colgroup">Revenue</th></tr>
                    <tr>
                        <th scope="row">Box office<small><?= number_format((int) ($fin['tickets_sold'] ?? 0)); ?> tickets sold</small></th>
                        <td class="num"><?= $money($val('ticket_revenue')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Concessions<small><?= number_format((int) ($fin['items_sold'] ?? 0)); ?> items sold</small></th>
                        <td class="num"><?= $money($val('concession_revenue')); ?></td>
                    </tr>
                    <tr class="total"><th scope="row">Gross revenue</th><td class="num"><?= $money($gross); ?></td></tr>

                    <tr class="group"><th colspan="2" scope="colgroup">Expenses</th></tr>
                    <tr>
                        <th scope="row">Cost of goods sold<small>Product replenishment</small></th>
                        <td class="num"><?= $money($val('cogs')); ?></td>
                    </tr>
                    <tr><th scope="row">Electricity</th><td class="num"><?= $money($val('electricity_cost')); ?></td></tr>
                    <tr><th scope="row">Water</th><td class="num"><?= $money($val('water_cost')); ?></td></tr>
                    <tr class="total"><th scope="row">Total expenses</th><td class="num"><?= $money($expenses); ?></td></tr>

                    <tr class="total">
                        <th scope="row">Net operating income</th>
                        <td class="num <?= $netClass; ?>"><?= $money($net); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-side">
        <div class="card">
            <h2 class="card-title">Revenue and costs</h2>
            <div class="chart-box"><canvas id="financeChart" role="img" aria-label="Bar chart of gross revenue, cost of goods, utilities and net income"></canvas></div>
        </div>
        <div class="card">
            <h2 class="card-title">Where the revenue comes from</h2>
            <div class="chart-box"><canvas id="revenueSplitChart" role="img" aria-label="Doughnut chart of box office versus concession revenue"></canvas></div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>window.ScreenBitesCharts = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL; ?>/assets/js/chart-analytics.js"></script>
