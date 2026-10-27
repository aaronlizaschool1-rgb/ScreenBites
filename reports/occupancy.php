<?php
/**
 * ScreenBites - Occupancy Analytics Report
 * Procedural reporting module modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Cinema Occupancy Analytics';
require_once dirname(__DIR__) . '/includes/header.php';

$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
$selectedDateEsc = mysqli_real_escape_string($conn, $selectedDate);

// 1. Fetch screenings and seat occupancy for the selected date
$sql = "SELECT s.screening_id, s.start_time, s.end_time, s.base_price,
               m.title AS movie_title, m.rating,
               h.hall_name,
               (h.total_rows * h.total_columns) AS total_capacity,
               COUNT(t.ticket_id) AS seats_sold
        FROM screenings s
        INNER JOIN movies m ON s.movie_id = m.movie_id
        INNER JOIN halls h ON s.hall_id = h.hall_id
        LEFT JOIN tickets t ON s.screening_id = t.screening_id AND t.ticket_status = 'Valid'
        WHERE s.screening_date = '{$selectedDateEsc}'
        GROUP BY s.screening_id
        ORDER BY h.hall_name ASC, s.start_time ASC";
$res = mysqli_query($conn, $sql);

$screenings = [];
$totalSeatsSold = 0;
$totalCapacity  = 0;

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $cap  = max(1, (int)$row['total_capacity']);
        $sold = (int)$row['seats_sold'];
        $rate = round(($sold / $cap) * 100, 1);

        $row['rate'] = $rate;
        $screenings[] = $row;

        $totalSeatsSold += $sold;
        $totalCapacity  += $cap;
    }
}

$overallOccupancyRate = $totalCapacity > 0 ? round(($totalSeatsSold / $totalCapacity) * 100, 1) : 0;

// 2. 30-Day Peak Showtimes (Hourly aggregation)
$peakRes = mysqli_query($conn, "
    SELECT 
        HOUR(s.start_time) AS hour_of_day,
        COUNT(t.ticket_id) AS total_tickets_sold
    FROM tickets t
    INNER JOIN screenings s ON t.screening_id = s.screening_id
    WHERE s.screening_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      AND t.ticket_status = 'Valid'
    GROUP BY HOUR(s.start_time)
    ORDER BY total_tickets_sold DESC
    LIMIT 5
");
?>

<div class="page-head">
    <div>
        <h1>Cinema Occupancy Analytics</h1>
        <p>Theater seat utilization rates for <?= date('l, F j, Y', strtotime($selectedDate)); ?>.</p>
    </div>
    
    <!-- Date picker filter form -->
    <form method="GET" action="<?= BASE_URL; ?>/reports/occupancy.php" style="display: flex; gap: 0.5rem; align-items: center;">
        <label for="date" style="font-weight: 600; font-size: 0.88rem; color: var(--c-muted);">Select Date:</label>
        <input type="date" id="date" name="date" value="<?= sanitize($selectedDate); ?>" class="form-control" style="width: auto; padding: 0.4rem 0.6rem;">
        <button type="submit" class="btn btn-sm btn-primary">Filter Date</button>
        <?php if ($selectedDate !== date('Y-m-d')): ?>
            <a href="<?= BASE_URL; ?>/reports/occupancy.php" class="btn btn-sm btn-secondary">Today</a>
        <?php endif; ?>
    </form>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Overall Occupancy Rate</p>
        <p class="kpi-value" style="color: <?= $overallOccupancyRate >= 70 ? '#1b5e35' : 'var(--c-indigo)'; ?>;">
            <?= $overallOccupancyRate; ?>%
        </p>
        <p class="kpi-note"><?= number_format($totalSeatsSold); ?> of <?= number_format($totalCapacity); ?> seats sold</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Tickets Reserved</p>
        <p class="kpi-value"><?= number_format($totalSeatsSold); ?> Seats</p>
        <p class="kpi-note">Across <?= count($screenings); ?> scheduled showtimes</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Available Theater Capacity</p>
        <p class="kpi-value" style="color: var(--c-muted);"><?= number_format(max(0, $totalCapacity - $totalSeatsSold)); ?></p>
        <p class="kpi-note">Unsold seats remaining</p>
    </div>
</div>

<!-- Screenings Occupancy Table -->
<div class="card" style="margin-top: 1.5rem;">
    <div style="padding: 1.25rem 1.25rem 0.5rem;">
        <h3 style="margin: 0; font-size: 1.15rem; color: var(--c-ink);">Screening Schedule Seat Utilization</h3>
        <p style="margin: 0.2rem 0 1rem; color: var(--c-muted); font-size: 0.85rem;">Individual showtime seat fill rates.</p>
    </div>

    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th>Movie Title &amp; Rating</th>
                    <th>Hall</th>
                    <th>Time Slot</th>
                    <th>Seats Booked</th>
                    <th>Capacity</th>
                    <th style="width: 220px;">Occupancy Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($screenings)): ?>
                    <?php foreach ($screenings as $sc): 
                        $rate = (float)$sc['rate'];
                        $sold = (int)$sc['seats_sold'];
                        $cap  = (int)$sc['total_capacity'];
                        $barColor = $rate >= 80 ? '#2f9e55' : ($rate < 30 ? 'var(--c-red)' : 'var(--c-indigo)');
                    ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($sc['movie_title']); ?></strong>
                                <span style="display: inline-block; margin-left: 0.4rem; padding: 0.05rem 0.4rem; font-size: 0.75rem; border: 1.5px solid var(--c-indigo); border-radius: 4px; color: var(--c-indigo); font-weight: 700;">
                                    <?= sanitize($sc['rating']); ?>
                                </span>
                            </td>
                            <td><?= sanitize($sc['hall_name']); ?></td>
                            <td>
                                <strong><?= date('g:i A', strtotime($sc['start_time'])); ?></strong>
                                <small style="color: var(--c-muted);">to <?= date('g:i A', strtotime($sc['end_time'])); ?></small>
                            </td>
                            <td>
                                <strong><?= number_format($sold); ?></strong>
                            </td>
                            <td><?= number_format($cap); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="flex: 1; height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden;">
                                        <div style="width: <?= min(100, $rate); ?>%; height: 100%; background: <?= $barColor; ?>;"></div>
                                    </div>
                                    <strong style="font-size: 0.85rem; width: 45px; text-align: right; color: <?= $barColor; ?>;">
                                        <?= $rate; ?>%
                                    </strong>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--c-muted); padding: 2.5rem;">
                            No screenings scheduled for <?= date('F j, Y', strtotime($selectedDate)); ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 30-Day Peak Hours Section -->
<div class="card" style="margin-top: 1.5rem; padding: 1.25rem;">
    <h3 style="margin: 0 0 0.35rem; font-size: 1.15rem; color: var(--c-ink);">Busiest Showtimes (Last 30 Days)</h3>
    <p style="margin: 0 0 1.25rem; color: var(--c-muted); font-size: 0.85rem;">Peak customer booking hours to assist in theater staffing.</p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
        <?php if ($peakRes && mysqli_num_rows($peakRes) > 0): ?>
            <?php while ($p = mysqli_fetch_assoc($peakRes)): 
                $hour = (int)$p['hour_of_day'];
                $timeFmt = date('g:i A', strtotime("$hour:00:00"));
            ?>
                <div style="padding: 1rem; background: #f8fafc; border: 1px solid var(--c-line); border-radius: 8px;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--c-muted); text-transform: uppercase;">Peak Slot</div>
                    <div style="font-size: 1.35rem; font-weight: 800; font-family: 'Bricolage Grotesque', sans-serif; color: var(--c-indigo); margin: 0.25rem 0;">
                        <?= $timeFmt; ?>
                    </div>
                    <div style="font-size: 0.82rem; color: var(--c-muted);">
                        <?= number_format((int)$p['total_tickets_sold']); ?> tickets booked
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: var(--c-muted);">No ticket history available.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
