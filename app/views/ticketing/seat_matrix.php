<?php

$e    = fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$peso = "\u{20B1}";
$fmt  = fn($n): string => number_format((float) $n, 2);

$screening   = is_array($screening ?? null) ? $screening : [];
$seats       = is_array($seats ?? null) ? $seats : [];
$holdTimeout = (int) ($holdTimeout ?? 600);
$screeningId = (int) ($screening['screening_id'] ?? 0);
$timeText    = fn($t): string => $t ? date('g:i A', strtotime((string) $t)) : '';

$phrase = [
    'available' => 'available',
    'held'      => 'held by another cashier',
    'heldbyme'  => 'held by you',
    'sold'      => 'sold',
];

$rows   = [];
$tiers  = [];
$maxCol = (int) ($screening['total_columns'] ?? 0);
$availableCount = 0;
foreach ($seats as $seat) {
    $rows[(string) $seat['seat_row']][] = $seat;
    $tiers[(string) $seat['tier_name']] = (float) $seat['calculated_price'];
    $maxCol = max($maxCol, (int) $seat['seat_number']);
    if (strtolower((string) $seat['seat_status']) === 'available') {
        $availableCount++;
    }
}
uksort($rows, 'strnatcasecmp');
foreach ($rows as $rowKey => $rowList) {
    usort($rowList, fn($a, $b) => (int) $a['seat_number'] <=> (int) $b['seat_number']);
    $rows[$rowKey] = $rowList;
}
asort($tiers);
$tierIndex = [];
$tierCount = 0;
foreach ($tiers as $tierName => $tierPrice) {
    $tierIndex[$tierName] = ++$tierCount;
}

$jsConfig = [
    'screeningId'  => $screeningId,
    'baseUrl'      => BASE_URL,
    'csrfToken'    => (string) ($csrfToken ?? ''),
    'holdTimeout'  => $holdTimeout,
    'pollMs'       => 5000,
    'screeningUrl' => BASE_URL . '/ticketing/screening/' . $screeningId,
    'checkoutUrl'  => BASE_URL . '/pos',
    'movieTitle'   => (string) ($screening['movie_title'] ?? ''),
    'hallName'     => (string) ($screening['hall_name'] ?? ''),
    'startTime'    => $timeText($screening['start_time'] ?? ''),
];
?>
<link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/seat-matrix.css">

<a class="back-link" href="<?= BASE_URL; ?>/schedules">Back to schedules</a>

<div class="page-head">
    <div>
        <h1><?= $e($screening['movie_title'] ?? 'Seat selection'); ?></h1>
        <p class="seat-meta">
            <?php if (!empty($screening['movie_rating'])): ?>
                <span class="rating-badge"><?= $e($screening['movie_rating']); ?></span>
            <?php endif; ?>
            <span><?= $e($screening['hall_name'] ?? ''); ?></span>
            <span><?= $e($timeText($screening['start_time'] ?? '')); ?> to <?= $e($timeText($screening['end_time'] ?? '')); ?></span>
            <span>Base price <?= $peso . $fmt($screening['base_price'] ?? 0); ?></span>
        </p>
    </div>
    <p class="seat-meta"><strong id="availCount"><?= $availableCount; ?></strong>&nbsp;of <?= count($seats); ?> seats available</p>
</div>

<p class="seat-msg" id="seatMsg" role="status" aria-live="polite"></p>

<?php if (!$seats): ?>
    <div class="card"><p class="empty-note">No seats are set up for this hall yet.</p></div>
<?php else: ?>
<div class="seatmap" id="seatMatrix">
    <div class="seat-layout">

        <section class="card" aria-label="Seat map">
            <div class="seat-screen">Screen</div>

            <div class="seat-scroll">
                <div class="seat-grid" style="--cols: <?= $maxCol; ?>" role="group" aria-label="Seats">
                <?php foreach ($rows as $rowName => $rowSeats): ?>
                    <div class="seat-row">
                        <span class="seat-row-label" aria-hidden="true"><?= $e($rowName); ?></span>
                        <?php foreach ($rowSeats as $seat):
                            $status = strtolower((string) $seat['seat_status']);
                            if (!isset($phrase[$status])) { $status = 'held'; }
                            $tierName = (string) $seat['tier_name'];
                            $price    = (float) $seat['calculated_price'];
                            $label    = 'Row ' . $seat['seat_row'] . ', seat ' . $seat['seat_number'] . ', ' . $tierName
                                      . ', ' . $peso . $fmt($price) . ', ' . $phrase[$status];
                            $secs     = isset($seat['seconds_remaining']) ? max(0, (int) $seat['seconds_remaining']) : $holdTimeout;
                        ?>
                        <button type="button"
                                class="seat tier-<?= (int) $tierIndex[$tierName]; ?>"
                                style="grid-column: <?= (int) $seat['seat_number'] + 1; ?>"
                                data-seat-id="<?= (int) $seat['seat_id']; ?>"
                                data-row="<?= $e($seat['seat_row']); ?>"
                                data-number="<?= (int) $seat['seat_number']; ?>"
                                data-tier="<?= $e($tierName); ?>"
                                data-price="<?= number_format($price, 2, '.', ''); ?>"
                                data-status="<?= $status; ?>"
                                <?= $status === 'heldbyme' ? 'data-seconds="' . $secs . '"' : ''; ?>
                                aria-pressed="<?= $status === 'heldbyme' ? 'true' : 'false'; ?>"
                                aria-disabled="<?= in_array($status, ['held', 'sold'], true) ? 'true' : 'false'; ?>"
                                aria-label="<?= $e($label); ?>">
                            <span class="seat-num"><?= (int) $seat['seat_number']; ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>

            <ul class="seat-legend" aria-label="Seat status legend">
                <li><span class="seat is-key" data-status="available" aria-hidden="true"></span>Available</li>
                <li><span class="seat is-key" data-status="heldbyme" aria-hidden="true"></span>Held by you</li>
                <li><span class="seat is-key" data-status="held" aria-hidden="true"></span>Held by another cashier</li>
                <li><span class="seat is-key" data-status="sold" aria-hidden="true"></span>Sold</li>
            </ul>
            <ul class="seat-legend tier-legend" aria-label="Seat tiers and prices">
                <?php foreach ($tiers as $tierName => $tierPrice): ?>
                    <li class="tier-<?= (int) $tierIndex[$tierName]; ?>">
                        <span class="tier-swatch" aria-hidden="true"></span>
                        <?= $e($tierName); ?>, <?= $peso . $fmt($tierPrice); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="seat-live" id="seatLive">Seat map updates live</p>
        </section>

        <aside class="seat-panel card" aria-labelledby="heldHeading">
            <h2 class="card-title" id="heldHeading">Your held seats</h2>
            <p class="hold-clock" id="holdClock">No seats held</p>
            <ul class="held-list" id="heldList"></ul>
            <p class="held-empty" id="heldEmpty">Select an available seat to hold it for <?= intdiv($holdTimeout, 60); ?> minutes.</p>
            <p class="held-total"><span>Total</span><strong id="heldTotal"><?= $peso; ?>0.00</strong></p>
            <div class="panel-actions">
                <button type="button" class="btn" id="checkoutBtn" disabled>Continue to checkout</button>
                <button type="button" class="btn btn-ghost" id="releaseAllBtn" disabled>Release all</button>
            </div>
            <p class="panel-note">Seats are released automatically if the hold runs out before checkout.</p>
        </aside>

    </div>
</div>

<script>window.SeatMatrixConfig = <?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="<?= BASE_URL; ?>/assets/js/seat-reservation.js"></script>
<?php endif; ?>
