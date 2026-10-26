<?php

$rows = is_array($screeningOccupancies ?? null) ? $screeningOccupancies : [];
$peak = is_array($peakHourStats ?? null) ? $peakHourStats : [];
$selectedDate = (string) ($selectedDate ?? date('Y-m-d'));

$occ = [];
$totalSold = 0;
$totalCap  = 0;
foreach ($rows as $r) {
    $cap  = (int) ($r['total_capacity'] ?? 0);
    $sold = (int) ($r['seats_sold'] ?? 0);
    $occ[] = [
        'movie_title' => (string) ($r['movie_title'] ?? ''),
        'hall_name'   => (string) ($r['hall_name'] ?? ''),
        'start_time'  => (string) ($r['start_time'] ?? ''),
        'sold'        => $sold,
        'cap'         => $cap,
        'rate'        => $cap > 0 ? round($sold / $cap * 100, 1) : 0.0,
    ];
    $totalSold += $sold;
    $totalCap  += $cap;
}
$overall = $totalCap > 0 ? round($totalSold / $totalCap * 100, 1) : 0.0;

$busiest = null;
$peakChart = [];
foreach ($peak as $p) {
    $h = (int) ($p['hour_of_day'] ?? 0);
    $t = (int) ($p['total_tickets_sold'] ?? 0);
    $peakChart[] = ['hour_of_day' => $h, 'total_tickets_sold' => $t];
    if ($busiest === null || $t > $busiest['t']) { $busiest = ['h' => $h, 't' => $t]; }
}

$timeText = fn(string $t): string => $t !== '' ? date('g:i A', strtotime($t)) : '';
$meterClass = fn(float $rate): string => $rate >= 80 ? 'is-high' : ($rate < 30 ? 'is-low' : '');

$chartData = ['occupancy' => $occ, 'peakHours' => $peakChart];
?>
<div class="page-head">
    <div>
        <h1>Occupancy analytics</h1>
        <p>Seats sold per screening on <?= htmlspecialchars(date('F j, Y', strtotime($selectedDate))); ?>, plus the busiest hours of the last 30 days.</p>
    </div>
</div>

<form class="report-filters" method="GET" action="<?= BASE_URL; ?>/reports/occupancy">
    <div class="field">
        <label for="date">Screening date</label>
        <input type="date" id="date" name="date" value="<?= htmlspecialchars($selectedDate); ?>">
    </div>
    <button type="submit" class="btn">Show report</button>
</form>

<div class="kpi-grid">
    <div class="kpi">
        <p class="kpi-label">Screenings</p>
        <p class="kpi-value"><?= count($occ); ?></p>
        <p class="kpi-note">On the selected date</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Seats sold</p>
        <p class="kpi-value"><?= number_format($totalSold); ?></p>
        <p class="kpi-note">Out of <?= number_format($totalCap); ?> seats</p>
    </div>
    <div class="kpi is-key">
        <p class="kpi-label">Overall occupancy</p>
        <p class="kpi-value"><?= number_format($overall, 1); ?>%</p>
        <p class="kpi-note">Seats sold divided by capacity</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Busiest hour</p>
        <p class="kpi-value"><?= $busiest ? date('g:00 A', mktime($busiest['h'], 0, 0, 1, 1, 2000)) : '&mdash;'; ?></p>
        <p class="kpi-note"><?= $busiest ? number_format($busiest['t']) . ' tickets in the last 30 days' : 'No sales in the last 30 days'; ?></p>
    </div>
</div>

<div class="report-grid is-even">
    <div class="card">
        <h2 class="card-title">Occupancy by screening</h2>
        <div class="chart-box" style="height: <?= max(220, count($occ) * 58); ?>px">
            <canvas id="occupancyChart" role="img" aria-label="Bar chart of occupancy percentage for each screening"></canvas>
        </div>
    </div>
    <div class="card">
        <h2 class="card-title">Peak hours (last 30 days)</h2>
        <div class="chart-box">
            <canvas id="peakHourChart" role="img" aria-label="Line chart of tickets sold for each hour of the day"></canvas>
        </div>
    </div>
</div>

<div class="card">
    <h2 class="card-title">Screening details</h2>
    <?php if (!$occ): ?>
        <p class="empty-note">No screenings are scheduled for this date. Pick another date.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="ledger">
                <thead>
                    <tr>
                        <th scope="col">Movie</th>
                        <th scope="col">Hall</th>
                        <th scope="col">Starts</th>
                        <th scope="col" class="num">Sold / capacity</th>
                        <th scope="col">Occupancy</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($occ as $o): ?>
                    <tr>
                        <th scope="row"><?= htmlspecialchars($o['movie_title']); ?></th>
                        <td><?= htmlspecialchars($o['hall_name']); ?></td>
                        <td><?= htmlspecialchars($timeText($o['start_time'])); ?></td>
                        <td class="num"><?= $o['sold']; ?> / <?= $o['cap']; ?></td>
                        <td>
                            <div class="meter <?= $meterClass($o['rate']); ?>">
                                <div class="meter-bar"><span style="width: <?= min(100, max(0, $o['rate'])); ?>%"></span></div>
                                <span class="meter-pct"><?= number_format($o['rate'], 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>window.ScreenBitesCharts = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL; ?>/assets/js/chart-analytics.js"></script>
