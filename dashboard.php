<?php
/**
 * ScreenBites - Management & Operational Dashboard
 * Modeled after PedalWorks-Dynamics procedural architecture
 * UI/UX styled from ScreenBites_oop_backup
 */

require_once __DIR__ . '/includes/config.php';
require_admin();

$pageTitle = 'Management Dashboard';
require_once __DIR__ . '/includes/header.php';

$currentUser = current_user();
$userName = sanitize($currentUser['full_name'] ?? 'Administrator');

// 1. Box Office & Concessions KPIs
$resGross = mysqli_query($conn, "SELECT COALESCE(SUM(subtotal_amount + tax_amount), 0) AS total_gross, COUNT(*) AS total_orders FROM orders WHERE order_status = 'Completed'");
$grossRow = mysqli_fetch_assoc($resGross);
$grossRevenue = (float)$grossRow['total_gross'];
$totalOrders  = (int)$grossRow['total_orders'];

$resTickets = mysqli_query($conn, "SELECT COUNT(*) AS total_tickets FROM tickets WHERE ticket_status = 'Valid'");
$ticketsRow = mysqli_fetch_assoc($resTickets);
$totalTickets = (int)$ticketsRow['total_tickets'];

$resSnacks = mysqli_query($conn, "
    SELECT 
        (SELECT COALESCE(SUM(quantity), 0) FROM order_items) +
        (SELECT COALESCE(SUM(quantity), 0) FROM order_combos) AS total_snacks
");
$snacksRow = mysqli_fetch_assoc($resSnacks);
$totalSnacks = (int)$snacksRow['total_snacks'];

// 2. Low-Stock Watchlist Query
$resLowStock = mysqli_query($conn, "
    SELECT product_id, product_name, category, current_stock, reorder_level 
    FROM products 
    WHERE is_active = 1 AND current_stock <= reorder_level 
    ORDER BY (current_stock <= 0) DESC, current_stock ASC 
    LIMIT 6
");
$lowStockAlerts = [];
if ($resLowStock) {
    while ($row = mysqli_fetch_assoc($resLowStock)) {
        $lowStockAlerts[] = $row;
    }
}
$lowStockCount = count($lowStockAlerts);

// 3. Today's Screenings
$resTodayScreenings = mysqli_query($conn, "
    SELECT s.screening_id, s.screening_date, s.start_time, s.end_time, s.base_price,
           m.title AS movie_title, m.rating AS movie_rating, h.hall_name,
           (SELECT COUNT(*) FROM tickets t WHERE t.screening_id = s.screening_id AND t.ticket_status = 'Valid') AS sold_seats_count
    FROM screenings s
    INNER JOIN movies m ON s.movie_id = m.movie_id
    INNER JOIN halls h ON s.hall_id = h.hall_id
    ORDER BY s.screening_date ASC, s.start_time ASC
    LIMIT 4
");
$todayScreenings = [];
if ($resTodayScreenings) {
    while ($row = mysqli_fetch_assoc($resTodayScreenings)) {
        $todayScreenings[] = $row;
    }
}

// 4. Recent Completed Transactions
$resRecentOrders = mysqli_query($conn, "
    SELECT o.order_id, o.order_number, (o.subtotal_amount + o.tax_amount) AS total_amount, o.order_status, o.created_at,
           u.full_name AS cashier_name,
           (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.order_id) AS ticket_count,
           ((SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.order_id) +
            (SELECT COALESCE(SUM(oc.quantity), 0) FROM order_combos oc WHERE oc.order_id = o.order_id)) AS concession_count
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    ORDER BY o.created_at DESC
    LIMIT 6
");
$recentOrders = [];
if ($resRecentOrders) {
    while ($row = mysqli_fetch_assoc($resRecentOrders)) {
        $recentOrders[] = $row;
    }
}

// 5. Utility Overhead for current year
$currentYear = (int)date('Y');
$resUtilTotals = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(bill_amount), 0) AS total_utilities,
        COALESCE(SUM(CASE WHEN utility_type = 'Electricity' THEN bill_amount ELSE 0 END), 0) AS total_electricity,
        COALESCE(SUM(CASE WHEN utility_type = 'Water' THEN bill_amount ELSE 0 END), 0) AS total_water
    FROM utility_expenses 
    WHERE billing_year = {$currentYear}
");
$utilTotals = mysqli_fetch_assoc($resUtilTotals);
$totalUtilities   = (float)$utilTotals['total_utilities'];
$totalElectricity = (float)$utilTotals['total_electricity'];
$totalWater       = (float)$utilTotals['total_water'];
$netOperatingMargin = $grossRevenue - $totalUtilities;
?>

<div class="sb-dash-wrapper">

    <!-- 1. DASHBOARD HERO BANNER -->
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
                <h1 class="dash-title">Welcome back, <?= $userName; ?></h1>
                <p class="dash-subtitle">Procedural management console for box office ticketing, snackbar concessions, and theater hall occupancy.</p>
            </div>

            <div class="dash-hero-actions">
                <a href="<?= BASE_URL; ?>/view_cart.php" class="btn dash-btn-cta">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    <span>Open POS Register</span>
                </a>
                <a href="<?= BASE_URL; ?>/schedules/index.php" class="btn dash-btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span>Hall Schedules</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 2. HIGH-IMPACT KPI METRICS -->
    <div class="dash-kpi-grid">
        <div class="dash-kpi-card kpi-card-revenue">
            <div class="dash-kpi-icon-wrap icon-revenue">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-header-row">
                    <span class="dash-kpi-label">Gross Sales Revenue</span>
                    <span class="dash-kpi-trend trend-up">▲ Active</span>
                </div>
                <strong class="dash-kpi-val"><?= format_currency($grossRevenue); ?></strong>
                <span class="dash-kpi-sub"><?= number_format($totalOrders); ?> completed transactions</span>
            </div>
        </div>

        <div class="dash-kpi-card kpi-card-tickets">
            <div class="dash-kpi-icon-wrap icon-tickets">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><circle cx="12" cy="15" r="2"></circle></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-header-row">
                    <span class="dash-kpi-label">Tickets Issued</span>
                    <span class="dash-kpi-trend trend-emerald">● Confirmed</span>
                </div>
                <strong class="dash-kpi-val"><?= number_format($totalTickets); ?></strong>
                <span class="dash-kpi-sub">Box office cinema admissions</span>
            </div>
        </div>

        <div class="dash-kpi-card kpi-card-concessions">
            <div class="dash-kpi-icon-wrap icon-concessions">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            </div>
            <div class="dash-kpi-body">
                <div class="dash-kpi-header-row">
                    <span class="dash-kpi-label">Concessions Dispensed</span>
                    <span class="dash-kpi-trend trend-gold">★ Snacks</span>
                </div>
                <strong class="dash-kpi-val"><?= number_format($totalSnacks); ?></strong>
                <span class="dash-kpi-sub">Snacks, drinks &amp; combo bundles</span>
            </div>
        </div>

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

    <!-- 3. WIDGET: OPERATIONAL QUICK ACCESS HUB -->
    <section class="dash-section" aria-label="Operations Quick Launch">
        <div class="dash-section-head">
            <div>
                <h2 class="dash-section-title">Operational Quick Access Hub</h2>
                <span class="dash-section-meta">Frequent cashier, ticketing, and cinema management workflows</span>
            </div>
            <span class="dash-hub-tag">Command Shortcuts</span>
        </div>

        <div class="dash-launchpad-grid">
            <a href="<?= BASE_URL; ?>/view_cart.php" class="dash-action-tile tile-pos">
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

            <a href="<?= BASE_URL; ?>/schedules/index.php" class="dash-action-tile tile-schedules">
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

            <a href="<?= BASE_URL; ?>/movies/index.php" class="dash-action-tile tile-movies">
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

            <a href="<?= BASE_URL; ?>/concessions/index.php" class="dash-action-tile tile-inventory">
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

    <!-- 4. SPLIT MAIN GRID -->
    <div class="dash-main-grid">

        <!-- LEFT COLUMN: Recent Transactions & Screenings -->
        <div class="dash-col-left">

            <section class="card dash-card" aria-label="Recent Transactions">
                <div class="dash-card-header">
                    <div>
                        <h2 class="dash-card-title">Recent Transactions</h2>
                        <p class="dash-card-sub">Latest completed cinema ticket reservations and snack purchases</p>
                    </div>
                    <a href="<?= BASE_URL; ?>/reports/sales.php" class="btn btn-sm btn-ghost dash-link-pill">
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="5" class="empty-note" style="text-align: center; color: var(--c-muted); padding: 2rem;">No recent transactions recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order):
                                    $ordNum = sanitize($order['order_number']);
                                    $tTotal = (float)$order['total_amount'];
                                    $tCount = (int)($order['ticket_count'] ?? 0);
                                    $sCount = (int)($order['concession_count'] ?? 0);
                                    $itemsDesc = [];
                                    if ($tCount > 0) $itemsDesc[] = "{$tCount}x Ticket" . ($tCount > 1 ? 's' : '');
                                    if ($sCount > 0) $itemsDesc[] = "{$sCount}x Snack" . ($sCount > 1 ? 's' : '');
                                    if (empty($itemsDesc)) $itemsDesc[] = "Concessions";
                                ?>
                                    <tr class="dash-order-row">
                                        <td>
                                            <code class="order-code"><?= $ordNum; ?></code>
                                        </td>
                                        <td>
                                            <div class="dash-actor-chip">
                                                <span class="actor-mini-dot"></span>
                                                <?= sanitize($order['cashier_name'] ?? 'Staff'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="order-items-pill"><?= implode(' + ', $itemsDesc); ?></span>
                                        </td>
                                        <td>
                                            <span class="dash-time-stamp"><?= date('M d, g:i A', strtotime($order['created_at'])); ?></span>
                                        </td>
                                        <td class="num">
                                            <strong class="dash-price-tag"><?= format_currency($tTotal); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Cinema Hall Screenings -->
            <section class="card dash-card" aria-label="Today Screenings" style="margin-top: 1.5rem;">
                <div class="dash-card-header">
                    <div>
                        <h2 class="dash-card-title">Cinema Hall Screenings</h2>
                        <p class="dash-card-sub">Active film showtimes and hall assignments</p>
                    </div>
                    <a href="<?= BASE_URL; ?>/schedules/index.php" class="btn btn-sm btn-ghost dash-link-pill">
                        All Schedules &rarr;
                    </a>
                </div>

                <?php if (empty($todayScreenings)): ?>
                    <div class="dash-empty-state" style="padding: 2.5rem; text-align: center; color: var(--c-muted);">
                        <p>No screenings scheduled yet.</p>
                        <a href="<?= BASE_URL; ?>/schedules/create.php" class="btn btn-sm dash-btn-cta" style="margin-top: 0.5rem;">Schedule a Showtime &rarr;</a>
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
                                        <?= sanitize($s['movie_rating'] ?? 'PG'); ?>
                                    </span>
                                    <span class="dash-sc-hall"><?= sanitize($s['hall_name'] ?? 'Cinema Hall'); ?></span>
                                </div>
                                <h3 class="dash-sc-title"><?= sanitize($s['movie_title']); ?></h3>
                                <div class="dash-sc-timing">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    <span><?= $sTime; ?> &minus; <?= $eTime; ?> (<?= $sDate; ?>)</span>
                                </div>
                                <div class="dash-sc-footer">
                                    <span class="dash-sc-price">Base: <?= format_currency($s['base_price']); ?></span>
                                    <a href="<?= BASE_URL; ?>/ticketing/seat_matrix.php?screening_id=<?= $sId; ?>" class="btn btn-sm dash-seat-btn">
                                        <span>Seats (<?= $sold; ?> Sold)</span> &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>

        <!-- RIGHT COLUMN: WIDGET: INVENTORY WATCHLIST & ANALYTICS PORTAL -->
        <div class="dash-col-right">

            <!-- INVENTORY WATCHLIST WIDGET -->
            <section class="card dash-card" aria-label="Inventory Watchlist">
                <div class="dash-card-header">
                    <div>
                        <h2 class="dash-card-title">Inventory Watchlist</h2>
                        <p class="dash-card-sub">Items at or below safety reorder threshold</p>
                    </div>
                    <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-sm btn-ghost dash-link-pill">Audit &rarr;</a>
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
                        <?php foreach ($lowStockAlerts as $item):
                            $cur = (int)$item['current_stock'];
                            $min = (int)$item['reorder_level'];
                            $isOut = ($cur <= 0);
                            $pct = $min > 0 ? min(100, (int)(($cur / $min) * 100)) : 0;
                        ?>
                            <div class="dash-stock-item <?= $isOut ? 'is-out' : ''; ?>">
                                <div class="dash-stock-info">
                                    <div class="dash-stock-name-row">
                                        <strong><?= sanitize($item['product_name']); ?></strong>
                                        <span class="stock-pill <?= $isOut ? 'pill-out' : 'pill-low'; ?>">
                                            <?= $isOut ? 'Out of Stock' : $cur . ' left'; ?>
                                        </span>
                                    </div>
                                    <div class="dash-stock-meta-row">
                                        <span><?= sanitize($item['category']); ?> &bull; Reorder Level: <?= $min; ?></span>
                                    </div>

                                    <div class="stock-meter-wrap">
                                        <div class="stock-meter-bar <?= $isOut ? 'bar-out' : 'bar-warn'; ?>" style="width: <?= max(8, $pct); ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dash-stock-footer">
                        <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-sm dash-view-all-stock">
                            <span>Manage Concessions Inventory (<?= $lowStockCount; ?> alerts)</span> &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ANALYTICS & INTELLIGENCE PORTAL -->
            <section class="card dash-card" aria-label="Financial and Operations Analytics" style="margin-top: 1.5rem;">
                <div class="dash-card-header">
                    <div>
                        <h2 class="dash-card-title">Analytics &amp; Intelligence</h2>
                        <p class="dash-card-sub">Financial ledgers, occupancy &amp; audit trails</p>
                    </div>
                </div>

                <div class="dash-portal-links">
                    <a href="<?= BASE_URL; ?>/reports/financial.php" class="dash-portal-item">
                        <div class="dash-portal-icon icon-fin">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>Net Operating Income</strong>
                            <span>Box office, concessions revenue, utility overhead &amp; margins</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>

                    <a href="<?= BASE_URL; ?>/reports/sales.php" class="dash-portal-item">
                        <div class="dash-portal-icon icon-sales">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line><polyline points="4 8 10 3 16 8 21 3"></polyline></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>Sales Report &amp; Trends</strong>
                            <span>Box office ticket earnings compared with snack bar revenue</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>

                    <a href="<?= BASE_URL; ?>/reports/occupancy.php" class="dash-portal-item">
                        <div class="dash-portal-icon icon-occ">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>Occupancy Analytics</strong>
                            <span>Hall seat utilization, popular showtimes &amp; fill rates</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>

                    <a href="<?= BASE_URL; ?>/utilities/index.php" class="dash-portal-item">
                        <div class="dash-portal-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>Utilities Overhead Ledger</strong>
                            <span>Monthly electricity (<?= format_currency($totalElectricity); ?>) &amp; water tracking</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>

                    <a href="<?= BASE_URL; ?>/audit/index.php" class="dash-portal-item">
                        <div class="dash-portal-icon" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        </div>
                        <div class="dash-portal-content">
                            <strong>System Audit Logs</strong>
                            <span>Compliance history and operator action audit trail</span>
                        </div>
                        <span class="portal-arrow">&rarr;</span>
                    </a>
                </div>
            </section>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
