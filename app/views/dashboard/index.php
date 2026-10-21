<?php
/**
 * ScreenBites: Modern Command Center Dashboard (FR12, FR13, FR14, FR15)
 * 
 * Central operational dashboard displaying real-time financial KPIs,
 * today's screening schedule, recent POS orders, inventory watchlist,
 * and quick-access operational launchpads.
 * 
 * @var array<string, mixed>             $kpis
 * @var array<int, array<string, mixed>> $todayScreenings
 * @var array<int, array<string, mixed>> $recentOrders
 * @var array<int, array<string, mixed>> $lowStockAlerts
 * @var string                           $todayDate
 */

use Core\Auth;

$e = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$peso = "\u{20B1}";
$fmt = fn($n): string => number_format((float)$n, 2);

$user = Auth::user();
$userName = rtrim((string)($user['full_name'] ?? 'Administrator'), '.');

$kpis            = is_array($kpis ?? null) ? $kpis : [];
$todayScreenings = is_array($todayScreenings ?? null) ? $todayScreenings : [];
$recentOrders    = is_array($recentOrders ?? null) ? $recentOrders : [];
$lowStockAlerts  = is_array($lowStockAlerts ?? null) ? $lowStockAlerts : [];

$totalRevenue     = (float)($kpis['total_revenue'] ?? 0.00);
$todayRevenue     = (float)($kpis['today_revenue'] ?? 0.00);
$ticketsSold      = (int)($kpis['tickets_sold'] ?? 0);
$concessionsSold  = (int)($kpis['concessions_sold'] ?? 0);
$lowStockCount    = (int)($kpis['low_stock_count'] ?? count($lowStockAlerts));
$activeMoviesCount= (int)($kpis['active_movies_count'] ?? 0);
?>

<!-- Dashboard Hero Header -->
<div class="dash-hero card">
    <div class="dash-hero-content">
        <div class="dash-hero-text">
            <div class="dash-badge-row">
                <span class="dash-live-badge"><span class="live-dot" aria-hidden="true"></span> Live Cinema Operations</span>
                <span class="dash-date-pill"><?= date('l, F j, Y'); ?></span>
            </div>
            <h1 class="dash-title">Welcome back, <?= $e($userName); ?>.</h1>
            <p class="dash-subtitle">Here is an overview of cinema ticket reservations, snack bar transactions, and operational metrics.</p>
        </div>
        <div class="dash-hero-actions">
            <a href="<?= BASE_URL; ?>/pos" class="btn dash-btn-cta">
                &#127871; Open POS Register
            </a>
            <a href="<?= BASE_URL; ?>/schedules" class="btn btn-ghost" style="color: #fff; border-color: rgba(255,255,255,.4);">
                &#127915; Hall Schedules
            </a>
        </div>
    </div>
</div>

<!-- Primary Operational KPI Cards -->
<div class="dash-kpi-grid">
    <div class="dash-kpi-card kpi-card-revenue">
        <div class="dash-kpi-icon" aria-hidden="true">&#128176;</div>
        <div class="dash-kpi-body">
            <span class="dash-kpi-label">Gross Sales Revenue</span>
            <strong class="dash-kpi-val"><?= $peso . $fmt($totalRevenue); ?></strong>
            <span class="dash-kpi-sub">Total ticket &amp; concessions sales</span>
        </div>
    </div>

    <div class="dash-kpi-card kpi-card-tickets">
        <div class="dash-kpi-icon" aria-hidden="true">&#127915;</div>
        <div class="dash-kpi-body">
            <span class="dash-kpi-label">Box Office Tickets Issued</span>
            <strong class="dash-kpi-val"><?= number_format($ticketsSold); ?></strong>
            <span class="dash-kpi-sub">Confirmed cinema admissions</span>
        </div>
    </div>

    <div class="dash-kpi-card kpi-card-concessions">
        <div class="dash-kpi-icon" aria-hidden="true">&#127871;</div>
        <div class="dash-kpi-body">
            <span class="dash-kpi-label">Concessions Dispensed</span>
            <strong class="dash-kpi-val"><?= number_format($concessionsSold); ?></strong>
            <span class="dash-kpi-sub">Snacks, drinks &amp; combo packages</span>
        </div>
    </div>

    <div class="dash-kpi-card kpi-card-stock <?= $lowStockCount > 0 ? 'is-warning' : 'is-healthy'; ?>">
        <div class="dash-kpi-icon" aria-hidden="true"><?= $lowStockCount > 0 ? '&#9888;' : '&#10003;'; ?></div>
        <div class="dash-kpi-body">
            <span class="dash-kpi-label">Inventory Threshold</span>
            <strong class="dash-kpi-val"><?= $lowStockCount; ?> <small style="font-size: .85rem; font-weight: 500;">alerts</small></strong>
            <span class="dash-kpi-sub"><?= $lowStockCount > 0 ? 'Items require stock replenishment' : 'All concession stocks healthy'; ?></span>
        </div>
    </div>
</div>

<!-- Quick Launchpad Actions -->
<section class="dash-section" aria-label="Operations Quick Launch">
    <div class="dash-section-head">
        <h2 class="dash-section-title">Operational Quick Access Hub</h2>
        <span class="dash-section-meta">Frequent cashier, ticketing and admin workflows</span>
    </div>

    <div class="dash-launchpad-grid">
        <a href="<?= BASE_URL; ?>/pos" class="dash-action-tile">
            <div class="action-tile-icon" style="background: #eef2ff; color: #4338ca;">&#127871;</div>
            <div class="action-tile-text">
                <strong>Snackbar POS Register</strong>
                <span>Quick-touch register for snacks, combos &amp; split checkout (FR8, FR9)</span>
            </div>
            <span class="action-tile-arrow">&rarr;</span>
        </a>

        <a href="<?= BASE_URL; ?>/schedules" class="dash-action-tile">
            <div class="action-tile-icon" style="background: #ecfdf5; color: #047857;">&#127915;</div>
            <div class="action-tile-text">
                <strong>Screening Schedules &amp; Seats</strong>
                <span>Assign hall slots, view occupancy &amp; reserve seats (FR1, FR2, FR4)</span>
            </div>
            <span class="action-tile-arrow">&rarr;</span>
        </a>

        <a href="<?= BASE_URL; ?>/movies" class="dash-action-tile">
            <div class="action-tile-icon" style="background: #fef2f2; color: #b91c1c;">&#127916;</div>
            <div class="action-tile-text">
                <strong>Movie Titles Catalog</strong>
                <span>Register cinema titles, MTRCB ratings &amp; upload poster art</span>
            </div>
            <span class="action-tile-arrow">&rarr;</span>
        </a>

        <a href="<?= BASE_URL; ?>/inventory" class="dash-action-tile">
            <div class="action-tile-icon" style="background: #fffbeb; color: #b45309;">&#128230;</div>
            <div class="action-tile-text">
                <strong>Concessions &amp; Stock Audits</strong>
                <span>Track inventory levels, margins &amp; override unit counts (FR5, FR7)</span>
            </div>
            <span class="action-tile-arrow">&rarr;</span>
        </a>
    </div>
</section>

<!-- 2-Column Operational Grid -->
<div class="dash-main-grid">
    <!-- LEFT COLUMN: Recent Activity & Screenings -->
    <div class="dash-col-left">

        <!-- Recent Completed Transactions -->
        <section class="card dash-card" aria-label="Recent Transactions">
            <div class="dash-card-header">
                <div>
                    <h2 class="dash-card-title">Recent Transactions</h2>
                    <p class="dash-card-sub">Latest completed cinema ticket reservations and snack purchases</p>
                </div>
                <a href="<?= BASE_URL; ?>/reports/sales" class="btn btn-sm btn-ghost">Full Sales Report &rarr;</a>
            </div>

            <div class="table-scroll">
                <table class="ledger dash-table">
                    <thead>
                        <tr>
                            <th>Receipt / Order #</th>
                            <th>Cashier</th>
                            <th>Items Included</th>
                            <th>Timestamp</th>
                            <th class="num">Grand Total</th>
                            <th style="text-align: right;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="6" class="empty-note">No recent transactions recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): 
                                $ordNum = (string)$order['order_number'];
                                $tTotal = (float)$order['total_amount'];
                                $tCount = (int)($order['ticket_count'] ?? 0);
                                $sCount = (int)($order['concession_count'] ?? 0);
                                $itemsDesc = [];
                                if ($tCount > 0) $itemsDesc[] = "{$tCount}x Ticket" . ($tCount > 1 ? 's' : '');
                                if ($sCount > 0) $itemsDesc[] = "{$sCount}x Snack" . ($sCount > 1 ? 's' : '');
                                if (empty($itemsDesc)) $itemsDesc[] = "General Order";
                            ?>
                                <tr>
                                    <td>
                                        <code class="order-code"><?= $e($ordNum); ?></code>
                                    </td>
                                    <td><?= $e($order['cashier_name'] ?? 'Staff'); ?></td>
                                    <td>
                                        <span class="order-items-pill"><?= implode(' + ', $itemsDesc); ?></span>
                                    </td>
                                    <td>
                                        <span class="dash-time-stamp"><?= date('M d, g:i A', strtotime($order['created_at'])); ?></span>
                                    </td>
                                    <td class="num">
                                        <strong><?= $peso . $fmt($tTotal); ?></strong>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="<?= BASE_URL; ?>/receipt/<?= $e($ordNum); ?>" class="btn btn-sm btn-ghost" target="_blank" rel="noopener">
                                            &#128424; Slip
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Today's Screening Schedules -->
        <section class="card dash-card" aria-label="Today Screenings">
            <div class="dash-card-header">
                <div>
                    <h2 class="dash-card-title">Cinema Hall Screenings</h2>
                    <p class="dash-card-sub">Active and upcoming film showtimes</p>
                </div>
                <a href="<?= BASE_URL; ?>/schedules" class="btn btn-sm btn-ghost">View All Schedules &rarr;</a>
            </div>

            <?php if (empty($todayScreenings)): ?>
                <p class="empty-note">No screenings currently scheduled. Click below to add showtimes.</p>
                <div style="text-align: center; padding-bottom: 1rem;">
                    <a href="<?= BASE_URL; ?>/schedules" class="btn btn-sm">Schedule a Showtime (FR1)</a>
                </div>
            <?php else: ?>
                <div class="dash-screenings-grid">
                    <?php foreach ($todayScreenings as $s): 
                        $sId = (int)$s['screening_id'];
                        $sold = (int)($s['sold_seats_count'] ?? 0);
                        $sTime = !empty($s['start_time']) ? date('g:i A', strtotime($s['start_time'])) : '';
                        $eTime = !empty($s['end_time']) ? date('g:i A', strtotime($s['end_time'])) : '';
                        $sDate = !empty($s['screening_date']) ? date('M d', strtotime($s['screening_date'])) : '';
                    ?>
                        <div class="dash-screening-card">
                            <div class="dash-sc-top">
                                <span class="rating-badge"><?= $e($s['movie_rating'] ?? 'PG'); ?></span>
                                <span class="dash-sc-hall"><?= $e($s['hall_name'] ?? 'Cinema Hall'); ?></span>
                            </div>
                            <h3 class="dash-sc-title"><?= $e($s['movie_title']); ?></h3>
                            <div class="dash-sc-timing">
                                <span>&#128337; <?= $sTime; ?> - <?= $eTime; ?> (<?= $sDate; ?>)</span>
                            </div>
                            <div class="dash-sc-footer">
                                <span class="dash-sc-price">Base: <?= $peso . $fmt($s['base_price']); ?></span>
                                <a href="<?= BASE_URL; ?>/ticketing/screening/<?= $sId; ?>" class="btn btn-sm">
                                    Seats (<?= $sold; ?> Sold) &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <!-- RIGHT COLUMN: Inventory Watchlist & Analytics Hub -->
    <div class="dash-col-right">

        <!-- Critical Inventory Watchlist Widget -->
        <section class="card dash-card" aria-label="Inventory Watchlist">
            <div class="dash-card-header">
                <div>
                    <h2 class="dash-card-title">Inventory Watchlist (FR7)</h2>
                    <p class="dash-card-sub">Items at or below safety reorder threshold</p>
                </div>
                <a href="<?= BASE_URL; ?>/inventory" class="btn btn-sm btn-ghost">Audit &rarr;</a>
            </div>

            <?php if (empty($lowStockAlerts)): ?>
                <div class="dash-stock-healthy">
                    <span class="stock-healthy-icon">&#10003;</span>
                    <div>
                        <strong>All Inventory Counts Healthy</strong>
                        <p>No snack or beverage products are currently below reorder levels.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="dash-stock-list">
                    <?php foreach (array_slice($lowStockAlerts, 0, 4) as $item): 
                        $cur = (int)$item['current_stock'];
                        $min = (int)$item['reorder_level'];
                        $isOut = ($cur <= 0);
                    ?>
                        <div class="dash-stock-item <?= $isOut ? 'is-out' : ''; ?>">
                            <div class="dash-stock-info">
                                <strong><?= $e($item['product_name']); ?></strong>
                                <span>Category: <?= $e($item['category']); ?> &bull; Threshold: <?= $min; ?></span>
                            </div>
                            <div class="dash-stock-status">
                                <span class="stock-pill <?= $isOut ? 'pill-out' : 'pill-low'; ?>">
                                    <?= $cur; ?> left
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="dash-stock-footer">
                    <a href="<?= BASE_URL; ?>/inventory" class="btn btn-sm btn-ghost btn-block">
                        View All <?= count($lowStockAlerts); ?> Reorder Alerts &rarr;
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Reports & Analytics Portal Links -->
        <section class="card dash-card" aria-label="Financial and Operations Analytics">
            <div class="dash-card-header">
                <div>
                    <h2 class="dash-card-title">Analytics &amp; Intelligence</h2>
                    <p class="dash-card-sub">Financial ledgers, occupancy &amp; audit trails</p>
                </div>
            </div>

            <div class="dash-portal-links">
                <a href="<?= BASE_URL; ?>/reports/financial" class="dash-portal-item">
                    <div class="dash-portal-icon" style="background: #eef2ff; color: #4338ca;">&#128178;</div>
                    <div class="dash-portal-content">
                        <strong>Net Operating Income</strong>
                        <span>Box office, concessions revenue, utility overhead &amp; margins (FR12)</span>
                    </div>
                </a>

                <a href="<?= BASE_URL; ?>/reports/sales" class="dash-portal-item">
                    <div class="dash-portal-icon" style="background: #ecfdf5; color: #047857;">&#128200;</div>
                    <div class="dash-portal-content">
                        <strong>Sales Report &amp; Trends</strong>
                        <span>Box office ticket earnings compared with snack bar revenue (FR14)</span>
                    </div>
                </a>

                <a href="<?= BASE_URL; ?>/reports/occupancy" class="dash-portal-item">
                    <div class="dash-portal-icon" style="background: #fffbeb; color: #b45309;">&#128202;</div>
                    <div class="dash-portal-content">
                        <strong>Hall Occupancy &amp; Peak Hours</strong>
                        <span>Identify peak business hours &amp; schedule projector power efficiently (FR15)</span>
                    </div>
                </a>

                <a href="<?= BASE_URL; ?>/expenses/utilities" class="dash-portal-item">
                    <div class="dash-portal-icon" style="background: #e0f2fe; color: #0369a1;">&#9889;</div>
                    <div class="dash-portal-content">
                        <strong>Utilities &amp; Operational Overhead</strong>
                        <span>Monthly Meralco electricity draw &amp; water sanitation expenses (FR11)</span>
                    </div>
                </a>

                <a href="<?= BASE_URL; ?>/audit" class="dash-portal-item">
                    <div class="dash-portal-icon" style="background: #fdf2f8; color: #be185d;">&#128274;</div>
                    <div class="dash-portal-content">
                        <strong>Transaction Audit Trail</strong>
                        <span>Cancellations, refunds, overrides &amp; failed sign-ins (FR16)</span>
                    </div>
                </a>
            </div>
        </section>

    </div>
</div>
