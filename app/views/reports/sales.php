<?php
/**
 * ScreenBites: Full Sales Report (FR14)
 * 
 * Comprehensive revenue analysis segregating box office admissions,
 * concession snack bars, film performance, daily timeline, and payment tenders.
 * 
 * @var array<string, mixed>             $sales
 * @var int                              $selectedYear
 * @var int                              $selectedMonth
 * @var array<int, array<string, mixed>> $salesTimeline
 * @var array<int, array<string, mixed>> $paymentBreakdown
 * @var array<int, array<string, mixed>> $topMovies
 * @var array<int, array<string, mixed>> $topConcessions
 */

$data = is_array($sales ?? null) ? $sales : (is_array($financial ?? null) ? $financial : []);
$val  = fn(string $key): float => (float)($data[$key] ?? 0);

$money = fn($amount): string => '&#8369;' . number_format((float)$amount, 2);
$share = fn(float $part, float $whole): float => $whole > 0 ? round($part / $whole * 100, 1) : 0.0;

$selectedYear  = (int)($selectedYear ?? date('Y'));
$selectedMonth = (int)($selectedMonth ?? date('n'));
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$years = range((int)date('Y') - 3, (int)date('Y') + 1);
if (!in_array($selectedYear, $years, true)) {
    $years[] = $selectedYear;
    sort($years);
}

$period = $data['period'] ?? (($monthNames[$selectedMonth] ?? '') . ' ' . $selectedYear);

$ticketRev   = $val('ticket_revenue');
$concRev     = $val('concession_revenue');
$totalSales  = $ticketRev + $concRev;
$ticketsQty  = (int)($data['tickets_sold'] ?? 0);
$itemsQty    = (int)($data['items_sold'] ?? 0);
$ordersCount = (int)($data['orders_count'] ?? 0);
$avgOrder    = $ordersCount > 0 ? ($totalSales / $ordersCount) : 0.0;
$avgTicket   = $ticketsQty > 0 ? ($ticketRev / $ticketsQty) : 0.0;
$avgItem     = $itemsQty > 0 ? ($concRev / $itemsQty) : 0.0;

$hasData = ($totalSales > 0 || !empty($salesTimeline) || !empty($paymentBreakdown));

$lines = [
    ['Box office', $ticketRev, $ticketsQty . ' tickets', $avgTicket, 'per ticket'],
    ['Concessions', $concRev, $itemsQty . ' items', $avgItem, 'per item'],
];

$chartData = [
    'revenueSplit' => [
        'ticket'     => $ticketRev,
        'concession' => $concRev
    ]
];
?>

<div class="page-head">
    <div>
        <h1>Comprehensive Sales Report</h1>
        <p>Box office admissions compared with concessions and tender settlement for <?= htmlspecialchars((string)$period); ?> (FR14).</p>
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

<?php if (!$hasData): ?>
    <div class="card">
        <p class="empty-note">
            No completed sales transactions found for <?= htmlspecialchars((string)$period); ?>.<br>
            Try selecting another billing month or recording ticket and concession sales.
        </p>
    </div>
<?php else: ?>

<!-- Top Performance KPIs -->
<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Total Gross Sales</p>
        <p class="kpi-value"><?= $money($totalSales); ?></p>
        <p class="kpi-note">Admissions + Snack Bar combined</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Box Office Sales</p>
        <p class="kpi-value"><?= $money($ticketRev); ?></p>
        <p class="kpi-note"><?= number_format($ticketsQty); ?> cinema tickets sold</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Concessions Revenue</p>
        <p class="kpi-value"><?= $money($concRev); ?></p>
        <p class="kpi-note"><?= number_format($itemsQty); ?> food &amp; drinks sold</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Transactions Completed</p>
        <p class="kpi-value"><?= number_format($ordersCount); ?></p>
        <p class="kpi-note">Avg order: <?= $money($avgOrder); ?></p>
    </div>
</div>

<!-- Sales Channel Breakdown & Doughnut Chart -->
<div class="report-grid">
    <div class="card">
        <h2 class="card-title">Revenue by Sales Channel</h2>
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
                <?php foreach ($lines as [$label, $rev, $qty, $avg, $unit]): $pct = $share($rev, $totalSales); ?>
                    <tr>
                        <th scope="row"><?= $label; ?><small><?= htmlspecialchars($qty); ?></small></th>
                        <td class="num"><strong><?= $money($rev); ?></strong></td>
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
                        <th scope="row">Total Gross Sales</th>
                        <td class="num"><strong><?= $money($totalSales); ?></strong></td>
                        <td></td>
                        <td><strong>100.0%</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Ticket vs. Concessions Split</h2>
        <div class="chart-box">
            <canvas id="revenueSplitChart" role="img" aria-label="Doughnut chart of box office versus concession revenue"></canvas>
        </div>
    </div>
</div>

<!-- Film & Snack Breakdown Grid -->
<div class="report-grid is-even">
    <!-- Top Movies by Box Office -->
    <div class="card">
        <h2 class="card-title">Box Office by Movie Title</h2>
        <div class="table-scroll">
            <table class="ledger">
                <thead>
                    <tr>
                        <th>Movie Title</th>
                        <th class="num">Tickets Sold</th>
                        <th class="num">Gross Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topMovies)): ?>
                        <tr><td colspan="3" class="empty-note">No ticket admissions recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topMovies as $m): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars((string)$m['movie_title']); ?></strong>
                                    <small><?= htmlspecialchars((string)($m['movie_rating'] ?? 'PG')); ?></small>
                                </td>
                                <td class="num"><?= number_format((int)$m['tickets_sold']); ?></td>
                                <td class="num"><strong><?= $money($m['movie_gross']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Concessions & Bundles -->
    <div class="card">
        <h2 class="card-title">Concessions &amp; Snacks Sold</h2>
        <div class="table-scroll">
            <table class="ledger">
                <thead>
                    <tr>
                        <th>Item / Package</th>
                        <th>Category</th>
                        <th class="num">Units Sold</th>
                        <th class="num">Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topConcessions)): ?>
                        <tr><td colspan="4" class="empty-note">No concession items sold.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topConcessions as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)$c['item_name']); ?></strong></td>
                                <td><small><?= htmlspecialchars((string)$c['category']); ?></small></td>
                                <td class="num"><?= number_format((int)$c['units_sold']); ?></td>
                                <td class="num"><strong><?= $money($c['total_sales']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Daily Sales Timeline & Payment Breakdown -->
<div class="report-grid is-even">
    <!-- Daily Timeline Table -->
    <div class="card">
        <h2 class="card-title">Daily Sales Timeline</h2>
        <div class="table-scroll">
            <table class="ledger">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="num">Box Office</th>
                        <th class="num">Concessions</th>
                        <th class="num">Day Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($salesTimeline)): ?>
                        <tr><td colspan="4" class="empty-note">No daily records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($salesTimeline as $day): ?>
                            <tr>
                                <td><strong><?= date('M d, Y', strtotime($day['sale_date'])); ?></strong></td>
                                <td class="num"><?= $money($day['box_office_sales']); ?></td>
                                <td class="num"><?= $money($day['concession_sales']); ?></td>
                                <td class="num"><strong><?= $money($day['total_sales']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Tender Breakdown -->
    <div class="card">
        <h2 class="card-title">Revenue by Payment Method</h2>
        <div class="table-scroll">
            <table class="ledger">
                <thead>
                    <tr>
                        <th>Payment Tender</th>
                        <th class="num">Transactions</th>
                        <th class="num">Net Collected</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paymentBreakdown)): ?>
                        <tr><td colspan="4" class="empty-note">No payment logs found.</td></tr>
                    <?php else: ?>
                        <?php 
                        $totalCollected = array_sum(array_column($paymentBreakdown, 'net_collected'));
                        foreach ($paymentBreakdown as $pay): 
                            $pPct = $share((float)$pay['net_collected'], (float)$totalCollected);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)$pay['payment_method']); ?></strong></td>
                                <td class="num"><?= number_format((int)$pay['transaction_count']); ?></td>
                                <td class="num"><strong><?= $money($pay['net_collected']); ?></strong></td>
                                <td>
                                    <div class="meter">
                                        <div class="meter-bar"><span style="width: <?= $pPct; ?>%"></span></div>
                                        <span class="meter-pct"><?= number_format($pPct, 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.ScreenBitesCharts = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL; ?>/assets/js/chart-analytics.js"></script>
<?php endif; ?>
