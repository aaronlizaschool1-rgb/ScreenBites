<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/seat_pricing.php';
require_admin();

$pageTitle = 'Seat Tiers & Pricing Configuration';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/seat-matrix.css">

<?php
$sql = "SELECT st.tier_id, st.tier_name, st.tier_surcharge, st.created_at,
               COUNT(s.seat_id) AS assigned_seats_count
        FROM seat_tiers st
        LEFT JOIN seats s ON st.tier_id = s.tier_id
        GROUP BY st.tier_id, st.tier_name, st.tier_surcharge, st.created_at
        ORDER BY st.tier_surcharge ASC, st.tier_id ASC";
$res = mysqli_query($conn, $sql);

$tiers = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $tiers[] = $row;
    }
}
?>

<div class="sb-page-header">
    <div>
        <h1>Seat Tiers &amp; Pricing Configuration</h1>
        <p>Manage cinema seat tier categories, pricing premiums, and surcharge multipliers.</p>
    </div>
    <div>
        <a href="<?= BASE_URL; ?>/schedules/index.php" class="btn btn-secondary">&larr; Schedules</a>
    </div>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="sb-alert sb-alert-success"><?= sanitize($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="sb-alert sb-alert-danger"><?= sanitize($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<div class="sb-card">
    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Seat Tier Category</th>
                    <th>Visual Badge</th>
                    <th>Tier Surcharge (Premium)</th>
                    <th>Pricing Formula</th>
                    <th style="text-align: center;">Assigned Seats</th>
                    <th style="text-align: right; width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tiers)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--c-muted, #5d6180); padding: 2.5rem;">
                            No seat tiers configured in system.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tiers as $tier):
                        $tId = (int)$tier['tier_id'];
                        $surcharge = (float)$tier['tier_surcharge'];
                        $tierClass = "tier-{$tId}";
                    ?>
                        <tr>
                            <td><code>#<?= $tId; ?></code></td>
                            <td>
                                <strong style="font-size: 1rem; color: var(--c-ink, #1d1f3a);"><?= sanitize($tier['tier_name']); ?></strong>
                            </td>
                            <td>
                                <div class="seatmap" style="display: inline-block;">
                                    <span class="seat tier-<?= $tId; ?>" style="cursor: default; pointer-events: none;" title="<?= sanitize($tier['tier_name']); ?>">
                                        <span class="seat-num"><?= $tId === 3 ? 'VIP' : ($tId === 2 ? 'CPL' : '01'); ?></span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if ($surcharge > 0): ?>
                                    <strong style="color: var(--accent-gold, #d97706); font-size: 1.05rem;">
                                        +<?= format_currency($surcharge); ?>
                                    </strong>
                                    <small style="color: var(--c-muted, #5d6180); display: block; font-size: 0.75rem;">Premium Add-on</small>
                                <?php else: ?>
                                    <span style="color: #10b981; font-weight: 700;">+₱0.00</span>
                                    <small style="color: var(--c-muted, #5d6180); display: block; font-size: 0.75rem;">Standard Base Price</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code style="background: #f1f3fa; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.82rem; color: var(--c-indigo, #2b2d7a);">
                                    Screening Base + <?= format_currency($surcharge); ?>
                                </code>
                            </td>
                            <td style="text-align: center;">
                                <span style="background: #eef2ff; color: var(--c-indigo, #2b2d7a); font-weight: 700; padding: 0.2rem 0.65rem; border-radius: 12px; font-size: 0.82rem; border: 1px solid #c7d2fe;">
                                    <?= (int)$tier['assigned_seats_count']; ?> seats
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="<?= BASE_URL; ?>/ticketing/edit_tier.php?id=<?= $tId; ?>" class="btn btn-sm btn-secondary">
                                    Edit Pricing
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
