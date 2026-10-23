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
$activeMoviesCount= (int)($kpis['active_movies_count'] ?? 30);
?>

<div class="sb-dash-wrapper">

    <!-- Dashboard Hero Header -->
    <div class="dash-hero card">
        <div class="dash-hero-backdrop" aria-hidden="true">
            <div class="dash-hero-glow"></div>
            <div class="dash-hero-grid-pattern"></div>
        </div>

        <div class="dash-hero-content">
            <div class="dash-hero-text">
                <div class="dash-badge-row">
                    <span class="dash-live-badge">
                        <span class="live-dot" aria-hidden="true"></span>
                        Live Cinema Operations
                    </span>
                    <span class="dash-date-pill">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <?= date('l, F j, Y'); ?>
                    </span>
                </div>
                <h1 class="dash-title">Welcome back, <?= $e($userName); ?></h1>
                <p class="dash-subtitle">Real-time overview of box office ticketing, snackbar concessions, and theater hall occupancy metrics.</p>
            </div>
            
            <div class="dash-hero-actions">
                <a href="<?= BASE_URL; ?>/pos" class="btn dash-btn-cta">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    <span>Open POS Register</span>
                </a>
                <a href="<?= BASE_URL; ?>/schedules" class="btn dash-btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span>Hall Schedules</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Primary Operational KPI Cards -->
    <div class="dash-kpi-grid">
        <!-- Revenue Card -->
        <div class="dash-kpi-card kpi-card-revenue">
            <div class="dash-kpi-icon-wrap icon-revenue">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-header-row">
                    <span class="dash-kpi-label">Gross Sales Revenue</span>
                    <span class="dash-kpi-trend trend-up">▲ Active</span>
                </div>
                <strong class="dash-kpi-val"><?= $peso . $fmt($totalRevenue); ?></strong>
                <span class="dash-kpi-sub">Total ticket &amp; snack bar revenue</span>
            </div>
        </div>

        <!-- Tickets Card -->
        <div class="dash-kpi-card kpi-card-tickets">
            <div class="dash-kpi-icon-wrap icon-tickets">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><circle cx="12" cy="15" r="2"></circle></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-header-row">
                    <span class="dash-kpi-label">Tickets Issued</span>
                    <span class="dash-kpi-trend trend-emerald">● Confirmed</span>
                </div>
                <strong class="dash-kpi-val"><?= number_format($ticketsSold); ?></strong>
                <span class="dash-kpi-sub">Box office cinema admissions</span>
            </div>
        </div>

        <!-- Concessions Card -->
        <div class="dash-kpi-card kpi-card-concessions">
            <div class="dash-kpi-icon-wrap icon-concessions">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-header-row">
                    <span class="dash-kpi-label">Concessions Dispensed</span>
                    <span class="dash-kpi-trend trend-gold">★ Snacks</span>
                </div>
                <strong class="dash-kpi-val"><?= number_format($concessionsSold); ?></strong>
                <span class="dash-kpi-sub">Snacks, drinks &amp; combo bundles</span>
            </div>
        </div>

        <!-- Inventory Threshold Card -->
        <div class="dash-kpi-card kpi-card-stock <?= $lowStockCount > 0 ? 'is-warning' : 'is-healthy'; ?>">
            <div class="dash-kpi-icon-wrap <?= $lowStockCount > 0 ? 'icon-warning' : 'icon-healthy'; ?>">
                <?php if ($lowStockCount > 0): ?>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <?php else: ?>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?php endif; ?>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-header-row">
                    <span class="dash-kpi-label">Inventory Watch</span>
                    <span class="dash-kpi-trend <?= $lowStockCount > 0 ? 'trend-danger' : 'trend-emerald'; ?>">
                        <?= $lowStockCount > 0 ? '● Alert' : '✓ In Stock'; ?>
                    </span>
                </div>
                <strong class="dash-kpi-val"><?= $lowStockCount; ?> <small style="font-size: .85rem; font-weight: 500;">alerts</small></strong>
                <span class="dash-kpi-sub"><?= $lowStockCount > 0 ? 'Items below reorder threshold' : 'All concessions stock optimal'; ?></span>
            </div>
        </div>
    </div>

    <!-- Quick Launchpad Actions -->
    <section class="dash-section" aria-label="Operations Quick Launch">
        <div class="dash-section-head">
            <div>
                <h2 class="dash-section-title">Operational Quick Access Hub</h2>
                <span class="dash-section-meta">Frequent cashier, ticketing, and hall management workflows</span>
            </div>
            <span class="dash-hub-tag">Command Shortcuts</span>
        </div>

        <div class="dash-launchpad-grid">
            <a href="<?= BASE_URL; ?>/pos" class="dash-action-tile tile-pos">
                <div class="action-tile-icon-box box-pos">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                </div>
                <div class="action-tile-text">
                    <strong>Snackbar POS Register</strong>
                    <span>Quick-touch register for snacks, combos &amp; split checkout</span>
                </div>
                <span class="action-tile-arrow-circle">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </span>
            </a>

            <a href="<?= BASE_URL; ?>/schedules" class="dash-action-tile tile-schedules">
                <div class="action-tile-icon-box box-schedules">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
                <div class="action-tile-text">
                    <strong>Screening Schedules &amp; Seats</strong>
                    <span>Assign hall slots, view seat occupancy &amp; reserve seats</span>
                </div>
                <span class="action-tile-arrow-circle">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </span>
            </a>

            <a href="<?= BASE_URL; ?>/movies" class="dash-action-tile tile-movies">
                <div class="action-tile-icon-box box-movies">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.5"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                </div>
                <div class="action-tile-text">
                    <strong>Movie Titles Catalog</strong>
                    <span>Now Showing showcase, ratings, posters &amp; trailers (30 Films)</span>
                </div>
                <span class="action-tile-arrow-circle">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </span>
            </a>

            <a href="<?= BASE_URL; ?>/inventory" class="dash-action-tile tile-inventory">
                <div class="action-tile-icon-box box-inventory">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                </div>
                <div class="action-tile-text">
                    <strong>Concessions &amp; Stock Audits</strong>
                    <span>Track inventory levels, margins &amp; override unit counts</span>
                </div>
                <span class="action-tile-arrow-circle">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </span>
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
                    <a href="<?= BASE_URL; ?>/reports/sales" class="btn btn-sm btn-ghost dash-link-pill">
                        Full Sales Report &rarr;
                    </a>
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
                                <th style="text-align: right;">Action</th>
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
                                    <tr class="dash-order-row">
                                        <td>
                                            <code class="order-code"><?= $e($ordNum); ?></code>
                                        </td>
                                        <td>
                                            <div class="dash-actor-chip">
                                                <span class="actor-mini-dot"></span>
                                                <?= $e($order['cashier_name'] ?? 'Staff'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="order-items-pill"><?= implode(' + ', $itemsDesc); ?></span>
                                        </td>
                                        <td>
                                            <span class="dash-time-stamp"><?= date('M d, g:i A', strtotime($order['created_at'])); ?></span>
                                        </td>
                                        <td class="num">
                                            <strong class="dash-price-tag"><?= $peso . $fmt($tTotal); ?></strong>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="<?= BASE_URL; ?>/receipt/<?= $e($ordNum); ?>" class="btn btn-sm dash-slip-btn" target="_blank" rel="noopener">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                                <span>Slip</span>
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
                        <p class="dash-card-sub">Active and upcoming film showtimes for today</p>
                    </div>
                    <a href="<?= BASE_URL; ?>/schedules" class="btn btn-sm btn-ghost dash-link-pill">
                        All Schedules &rarr;
                    </a>
                </div>

                <?php if (empty($todayScreenings)): ?>
                    <div class="dash-empty-state">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <p>No screenings scheduled yet for today.</p>
                        <a href="<?= BASE_URL; ?>/schedules" class="btn btn-sm dash-btn-cta">Schedule a Showtime &rarr;</a>
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
                                    <span class="rating-badge rating-<?= strtolower(str_replace('-', '', (string)($s['movie_rating'] ?? 'PG'))); ?>">
                                        <?= $e($s['movie_rating'] ?? 'PG'); ?>
                                    </span>
                                    <span class="dash-sc-hall"><?= $e($s['hall_name'] ?? 'Cinema Hall'); ?></span>
                                </div>
                                <h3 class="dash-sc-title"><?= $e($s['movie_title']); ?></h3>
                                <div class="dash-sc-timing">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    <span><?= $sTime; ?> - <?= $eTime; ?> (<?= $sDate; ?>)</span>
                                </div>
                                <div class="dash-sc-footer">
                                    <span class="dash-sc-price">Base: <?= $peso . $fmt($s['base_price']); ?></span>
                                    <a href="<?= BASE_URL; ?>/ticketing/screening/<?= $sId; ?>" class="btn btn-sm dash-seat-btn">
                                        <span>Seats (<?= $sold; ?> Sold)</span> &rarr;
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
                    <a href="<?= BASE_URL; ?>/inventory" class="btn btn-sm btn-ghost dash-link-pill">Audit &rarr;</a>
                </div>

                <?php if (empty($lowStockAlerts)): ?>
                    <div class="dash-stock-healthy">
                        <div class="stock-healthy-badge">✓</div>
                        <div>
                            <strong>All Concessions Optimal</strong>
                            <p>No snack or beverage products are currently below safety reorder levels.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="dash-stock-list">
                        <?php foreach (array_slice($lowStockAlerts, 0, 4) as $item): 
                            $cur = (int)$item['current_stock'];
                            $min = (int)$item['reorder_level'];
                            $isOut = ($cur <= 0);
                            $pct = $min > 0 ? min(100, (int)(($cur / $min) * 100)) : 0;
                        ?>
                            <div class="dash-stock-item <?= $isOut ? 'is-out' : ''; ?>">
                                <div class="dash-stock-info">
                                    <div class="dash-stock-name-row">
                                        <strong><?= $e($item['product_name']); ?></strong>
                                        <span class="stock-pill <?= $isOut ? 'pill-out' : 'pill-low'; ?>">
                                            <?= $isOut ? 'Out of Stock' : $cur . ' left'; ?>
                                        </span>
                                    </div>
                                    <div class="dash-stock-meta-row">
                                        <span><?= $e($item['category']); ?> &bull; Threshold: <?= $min; ?></span>
                                    </div>
                                    <!-- Visual Stock Progress Bar -->
                                    <div class="stock-meter-wrap">
                                        <div class="stock-meter-bar <?= $isOut ? 'bar-out' : 'bar-warn'; ?>" style="width: <?= max(8, $pct); ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dash-stock-footer">
                        <a href="<?= BASE_URL; ?>/inventory" class="btn btn-sm dash-view-all-stock">
                            <span>View All <?= count($lowStockAlerts); ?> Reorder Alerts</span> &rarr;
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
                        <div class="dash-portal-icon icon-fin">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>Net Operating Income</strong>
                            <span>Box office, concessions revenue, utility overhead &amp; margins (FR12)</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>

                    <a href="<?= BASE_URL; ?>/reports/sales" class="dash-portal-item">
                        <div class="dash-portal-icon icon-sales">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line><polyline points="4 8 10 3 16 8 21 3"></polyline></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>Sales Report &amp; Trends</strong>
                            <span>Box office ticket earnings compared with snack bar revenue (FR14)</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>

                    <a href="<?= BASE_URL; ?>/reports/occupancy" class="dash-portal-item">
                        <div class="dash-portal-icon icon-occ">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>Hall Occupancy Analytics</strong>
                            <span>Identify peak business hours &amp; schedule projector power efficiently (FR15)</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>

                    <a href="<?= BASE_URL; ?>/expenses/utilities" class="dash-portal-item">
                        <div class="dash-portal-icon icon-util">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>Utilities &amp; Operational Overhead</strong>
                            <span>Electricity draw &amp; water sanitation expense logs (FR11)</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>

                    <a href="<?= BASE_URL; ?>/audit" class="dash-portal-item">
                        <div class="dash-portal-icon icon-audit">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>Transaction Audit Trail</strong>
                            <span>Cancellations, refunds, overrides &amp; failed sign-ins (FR16)</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>
                </div>
            </section>

        </div>
    </div>

</div>
