<?php
/**
 * ScreenBites - Visual Seat Selection Matrix
 * Direct 1:1 UI/UX recreation modeled after ScreenBites_oop_backup
 * Implemented with clean, 100% procedural architecture
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/seat_pricing.php';
require_once dirname(__DIR__) . '/includes/seat_holds.php';

$screeningId = (int)($_GET['screening_id'] ?? 0);

if ($screeningId <= 0) {
    header("Location: " . BASE_URL . "/schedules/index.php");
    exit;
}

// 1. Fetch screening details
$sql = "SELECT s.screening_id, s.screening_date, s.start_time, s.end_time, s.base_price, 
               m.movie_id, m.title AS movie_title, m.poster_path, m.duration_minutes, m.rating,
               h.hall_id, h.hall_name, h.total_columns
        FROM screenings s
        INNER JOIN movies m ON s.movie_id = m.movie_id
        INNER JOIN halls h ON s.hall_id = h.hall_id
        WHERE s.screening_id = {$screeningId} LIMIT 1";

$res = mysqli_query($conn, $sql);
$screening = mysqli_fetch_assoc($res);

if (!$screening) {
    header("Location: " . BASE_URL . "/schedules/index.php");
    exit;
}

// Concurrency & Expiration Protection: Block past / already-started screenings
$screeningStartTimestamp = strtotime($screening['screening_date'] . ' ' . $screening['start_time']);
if ($screeningStartTimestamp && $screeningStartTimestamp < time()) {
    set_flash('error', 'This screening started at ' . date('M d, g:i A', $screeningStartTimestamp) . ' and is closed for new bookings.');
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$hallId = (int)$screening['hall_id'];
$basePrice = (float)$screening['base_price'];

// 2. Fetch all seat tiers
$seatTiers = get_seat_tiers($conn);

// 3. Fetch already-sold seats for this screening
$soldRes = mysqli_query($conn, "SELECT seat_id FROM tickets WHERE screening_id = {$screeningId} AND ticket_status = 'Valid'");
$soldSeatIds = [];
while ($soldRow = mysqli_fetch_assoc($soldRes)) {
    $soldSeatIds[] = (int)$soldRow['seat_id'];
}

// 4. Fetch active seat holds (FR4: Seat Hold & Timeout)
$currentSessionToken = session_id();
$activeHolds = get_active_seat_holds_for_screening($conn, $screeningId);

// 5. Fetch all seats in this cinema hall with tier information
$rowsGrouped = get_seats_with_tier_info($conn, $hallId);

// Calculate total seats & availability stats
$totalSeatsCount = 0;
$availableCount = 0;
$maxCol = (int)($screening['total_columns'] ?? 8);

foreach ($rowsGrouped as $rowLetter => $seatsInRow) {
    foreach ($seatsInRow as $seat) {
        $totalSeatsCount++;
        $sId = (int)$seat['seat_id'];
        $num = (int)$seat['seat_number'];
        if ($num > $maxCol) $maxCol = $num;
        
        $isSold = in_array($sId, $soldSeatIds, true);
        $isHeldOther = isset($activeHolds[$sId]) && ($activeHolds[$sId]['session_token'] !== $currentSessionToken);
        if (!$isSold && !$isHeldOther) {
            $availableCount++;
        }
    }
}

$pageTitle = 'Seat Selection - ' . $screening['movie_title'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/seat-matrix.css">

<a class="back-link" href="<?= BASE_URL; ?>/schedules/index.php?date=<?= urlencode($screening['screening_date']); ?>">&larr; Back to schedules</a>

<div class="page-head" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <h1 style="margin: 0 0 0.4rem; font-size: 1.8rem; font-weight: 800;"><?= sanitize($screening['movie_title']); ?></h1>
        <p class="seat-meta">
            <?php if (!empty($screening['rating'])): ?>
                <span class="rating-badge"><?= sanitize($screening['rating']); ?></span>
            <?php endif; ?>
            <span><strong><?= sanitize($screening['hall_name']); ?></strong></span>
            <span><?= date('g:i A', strtotime($screening['start_time'])); ?> to <?= date('g:i A', strtotime($screening['end_time'])); ?></span>
            <span>Base price <strong><?= format_currency($screening['base_price']); ?></strong></span>
        </p>
    </div>
    <p class="seat-meta" style="font-size: 1rem;">
        <strong id="availCount" style="color: #10b981; font-size: 1.25rem;"><?= $availableCount; ?></strong>&nbsp;of <?= $totalSeatsCount; ?> seats available
    </p>
</div>

<div class="seatmap" id="seatMatrix">
    <!-- TWO-COLUMN LAYOUT MATCHING ScreenBites_oop_backup -->
    <div class="seat-layout">

        <!-- LEFT COLUMN: CINEMA SCREEN & INTERACTIVE SEAT GRID -->
        <section class="sb-card" style="padding: 1.5rem; text-align: center;" aria-label="Seat map">
            <div class="seat-screen">Cinema Screen</div>

            <div class="seat-scroll">
                <div class="seat-grid" style="--cols: <?= $maxCol; ?>" role="group" aria-label="Seats">
                    <?php foreach ($rowsGrouped as $rowName => $rowSeats): ?>
                        <div class="seat-row">
                            <span class="seat-row-label" aria-hidden="true"><?= sanitize($rowName); ?></span>
                            <?php foreach ($rowSeats as $st): 
                                $seatId = (int)$st['seat_id'];
                                $isSold = in_array($seatId, $soldSeatIds, true);
                                $isHeldByOther = false;
                                $isHeldByMe = false;

                                if (isset($activeHolds[$seatId])) {
                                    if ($activeHolds[$seatId]['session_token'] === $currentSessionToken) {
                                        $isHeldByMe = true;
                                    } else {
                                        $isHeldByOther = true;
                                    }
                                }

                                // Status: available, sold, held, or heldbyme
                                $status = 'available';
                                if ($isSold) {
                                    $status = 'sold';
                                } elseif ($isHeldByOther) {
                                    $status = 'held';
                                } elseif ($isHeldByMe) {
                                    $status = 'heldbyme';
                                }

                                $tId = (int)$st['tier_id'];
                                $tierName = $st['tier_name'];
                                $surcharge = (float)$st['tier_surcharge'];
                                $computedPrice = calculate_seat_price($basePrice, $surcharge);
                                $seatNum = (int)$st['seat_number'];
                                $label = "Row {$rowName}, Seat {$seatNum}, {$tierName}, " . format_currency($computedPrice) . ", {$status}";
                            ?>
                                <button type="button"
                                        class="seat tier-<?= $tId; ?>"
                                        style="grid-column: <?= $seatNum + 1; ?>"
                                        data-seat-id="<?= $seatId; ?>"
                                        data-row="<?= sanitize($rowName); ?>"
                                        data-number="<?= $seatNum; ?>"
                                        data-tier-id="<?= $tId; ?>"
                                        data-tier="<?= sanitize($tierName); ?>"
                                        data-price="<?= number_format($computedPrice, 2, '.', ''); ?>"
                                        data-status="<?= $status; ?>"
                                        aria-pressed="<?= ($status === 'heldbyme') ? 'true' : 'false'; ?>"
                                        aria-disabled="<?= in_array($status, ['held', 'sold'], true) ? 'true' : 'false'; ?>"
                                        aria-label="<?= sanitize($label); ?>">
                                    <span class="seat-num"><?= $seatNum; ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- STATUS LEGEND (ScreenBites_oop_backup) -->
            <ul class="seat-legend" aria-label="Seat status legend">
                <li><span class="seat is-key" data-status="available" aria-hidden="true"></span>Available</li>
                <li><span class="seat is-key" data-status="selected" aria-hidden="true"></span>Selected / Held by you</li>
                <li><span class="seat is-key" data-status="held" aria-hidden="true"></span>Held by another customer</li>
                <li><span class="seat is-key" data-status="sold" aria-hidden="true"></span>Sold</li>
            </ul>

            <!-- TIER PRICING LEGEND (ScreenBites_oop_backup) -->
            <ul class="seat-legend tier-legend" aria-label="Seat tiers and prices">
                <?php foreach ($seatTiers as $tId => $tier): 
                    $surcharge = (float)$tier['tier_surcharge'];
                    $tierPrice = calculate_seat_price($basePrice, $surcharge);
                ?>
                    <li class="tier-<?= $tId; ?>">
                        <span class="tier-swatch" style="background: <?= $tId === 3 ? '#1f9d8a' : ($tId === 2 ? '#e98a94' : '#9ea1d6'); ?>;" aria-hidden="true"></span>
                        <strong><?= sanitize($tier['tier_name']); ?>:</strong> <?= format_currency($tierPrice); ?>
                        <?php if ($surcharge > 0): ?>
                            <small style="color: var(--text-muted);">(+<?= format_currency($surcharge); ?>)</small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- RIGHT COLUMN: STICKY 'YOUR HELD SEATS' SUMMARY PANEL -->
        <aside class="seat-panel sb-card" style="padding: 1.5rem;" aria-labelledby="heldHeading">
            <h2 class="card-title" id="heldHeading" style="font-size: 1.25rem; font-weight: 700; margin: 0 0 0.5rem;">
                Your Selected Seats
            </h2>
            <p class="hold-clock" id="holdClock">
                <span>⏱️</span> <span id="holdClockText">No seats selected</span>
            </p>

            <ul class="held-list" id="heldList"></ul>

            <p class="held-empty" id="heldEmpty">
                Click on available seats on the map to hold and reserve them for 8 minutes.
            </p>

            <div class="held-total">
                <span>Total Ticket Price</span>
                <strong id="heldTotal">₱0.00</strong>
            </div>

            <!-- NATIVE PROCEDURAL POST FORM -->
            <form method="POST" action="<?= BASE_URL; ?>/cart_update.php" id="seatSubmitForm">
                <input type="hidden" name="type" value="add_tickets">
                <input type="hidden" name="screening_id" value="<?= $screeningId; ?>">
                <div id="hiddenSeatInputs"></div>

                <div class="panel-actions">
                    <button type="submit" class="btn btn-primary" id="checkoutBtn" disabled style="padding: 0.75rem 1rem; font-weight: 700; font-size: 1rem;">
                        Continue to Checkout &rarr;
                    </button>
                    <button type="button" class="btn btn-secondary" id="releaseAllBtn" disabled>
                        Clear Selection
                    </button>
                </div>
            </form>

            <p class="panel-note">
                Selected seats will be held exclusively for you for 8 minutes to complete snacks selection and payment.
            </p>
        </aside>

    </div>
</div>

<!-- LIGHTWEIGHT PROCEDURAL JAVASCRIPT CONTROLLER (NO OOP / NO COMPLEX ASYNC ENGINE) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var selectedSeats = {};
    var seatButtons = document.querySelectorAll('.seat[data-status="available"], .seat[data-status="heldbyme"]');
    var heldList = document.getElementById('heldList');
    var heldEmpty = document.getElementById('heldEmpty');
    var heldTotal = document.getElementById('heldTotal');
    var checkoutBtn = document.getElementById('checkoutBtn');
    var releaseAllBtn = document.getElementById('releaseAllBtn');
    var holdClockText = document.getElementById('holdClockText');
    var hiddenInputsContainer = document.getElementById('hiddenSeatInputs');

    // Pre-select any seats already in user's hold
    document.querySelectorAll('.seat[data-status="heldbyme"]').forEach(function(btn) {
        toggleSeat(btn, true);
    });

    seatButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var curStatus = this.getAttribute('data-status');
            if (curStatus === 'sold' || curStatus === 'held') return;

            var isSelected = (curStatus === 'selected' || curStatus === 'heldbyme');
            toggleSeat(this, !isSelected);
        });
    });

    releaseAllBtn.addEventListener('click', function() {
        Object.keys(selectedSeats).forEach(function(sId) {
            var btn = document.querySelector('.seat[data-seat-id="' + sId + '"]');
            if (btn) toggleSeat(btn, false);
        });
    });

    function toggleSeat(btn, select) {
        var seatId = btn.getAttribute('data-seat-id');
        var row = btn.getAttribute('data-row');
        var num = btn.getAttribute('data-number');
        var tier = btn.getAttribute('data-tier');
        var price = parseFloat(btn.getAttribute('data-price')) || 0;
        var label = row + num;

        if (select) {
            btn.setAttribute('data-status', 'selected');
            btn.setAttribute('aria-pressed', 'true');
            selectedSeats[seatId] = {
                id: seatId,
                label: label,
                tier: tier,
                price: price
            };
        } else {
            btn.setAttribute('data-status', 'available');
            btn.setAttribute('aria-pressed', 'false');
            delete selectedSeats[seatId];
        }

        renderPanel();
    }

    function renderPanel() {
        heldList.innerHTML = '';
        hiddenInputsContainer.innerHTML = '';
        var count = Object.keys(selectedSeats).length;
        var total = 0;

        if (count === 0) {
            heldEmpty.style.display = 'block';
            checkoutBtn.disabled = true;
            releaseAllBtn.disabled = true;
            holdClockText.textContent = 'No seats selected';
            heldTotal.textContent = '₱0.00';
            return;
        }

        heldEmpty.style.display = 'none';
        checkoutBtn.disabled = false;
        releaseAllBtn.disabled = false;
        holdClockText.textContent = count + ' seat(s) ready to hold';

        Object.values(selectedSeats).forEach(function(st) {
            total += st.price;

            // 1. Render item in side list
            var li = document.createElement('li');
            li.className = 'held-item';
            li.innerHTML = 
                '<div class="held-seat">' +
                    '<strong>Seat ' + st.label + '</strong>' +
                    '<small>' + st.tier + ' Tier</small>' +
                '</div>' +
                '<div class="held-price">₱' + st.price.toFixed(2) + '</div>' +
                '<button type="button" class="held-remove" data-id="' + st.id + '" title="Remove seat">✕</button>';
            heldList.appendChild(li);

            // 2. Render hidden input for native POST submission
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'seats[]';
            input.value = st.id;
            hiddenInputsContainer.appendChild(input);
        });

        heldTotal.textContent = '₱' + total.toFixed(2);

        // Bind remove button handlers in the side list
        heldList.querySelectorAll('.held-remove').forEach(function(rmBtn) {
            rmBtn.addEventListener('click', function() {
                var sId = this.getAttribute('data-id');
                var seatBtn = document.querySelector('.seat[data-seat-id="' + sId + '"]');
                if (seatBtn) {
                    toggleSeat(seatBtn, false);
                }
            });
        });
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
