<?php
/**
 * ScreenBites - Visual Seat Selection Matrix
 * Checkbox-driven cinema seating grid adding directly to session cart
 * Integrated with FR3: Tier-Based Seat Pricing (Procedural Architecture)
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/seat_pricing.php';

$screeningId = (int)($_GET['screening_id'] ?? 0);

if ($screeningId <= 0) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// 1. Fetch screening details
$sql = "SELECT s.screening_id, s.screening_date, s.start_time, s.end_time, s.base_price, 
               m.movie_id, m.title AS movie_title, m.poster_path, m.duration_minutes, m.rating,
               h.hall_id, h.hall_name
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

// Concurrency & Expiration Protection: Block past concluded screenings
$screeningEndTimestamp = strtotime($screening['screening_date'] . ' ' . $screening['end_time']);
if ($screeningEndTimestamp && $screeningEndTimestamp < time()) {
    set_flash('error', 'This screening has already concluded at ' . date('g:i A', $screeningEndTimestamp) . ' and is closed for booking.');
    header("Location: " . BASE_URL . "/schedules/index.php?date=" . urlencode($screening['screening_date']));
    exit;
}

$hallId = (int)$screening['hall_id'];
$basePrice = (float)$screening['base_price'];

// 2. Fetch all seat tiers for dynamic legend and calculations
$seatTiers = get_seat_tiers($conn);

// 3. Fetch already-sold seats for this screening
$soldRes = mysqli_query($conn, "SELECT seat_id FROM tickets WHERE screening_id = {$screeningId} AND ticket_status = 'Valid'");
$soldSeatIds = [];
while ($soldRow = mysqli_fetch_assoc($soldRes)) {
    $soldSeatIds[] = (int)$soldRow['seat_id'];
}

// 4. Fetch all seats in this cinema hall with tier information
$rowsGrouped = get_seats_with_tier_info($conn, $hallId);

$pageTitle = 'Select Seats - ' . $screening['movie_title'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sb-page-header">
    <div>
        <h1>Select Your Seats</h1>
        <p>
            <strong><?= sanitize($screening['movie_title']); ?></strong> &bull; 
            <?= sanitize($screening['hall_name']); ?> &bull; 
            <?= date('M d, Y - g:i A', strtotime($screening['screening_date'] . ' ' . $screening['start_time'])); ?>
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= BASE_URL; ?>/index.php" class="btn btn-secondary">&larr; Back to Movies</a>
        <a href="<?= BASE_URL; ?>/view_cart.php" class="btn btn-secondary">View Cart</a>
    </div>
</div>

<div class="sb-card" style="text-align: center; padding: 2rem 1.5rem;">

    <!-- DYNAMIC TIER-BASED SEAT LEGEND -->
    <div class="tier-legend-strip">
        <?php foreach ($seatTiers as $tId => $tier): 
            $surcharge = (float)$tier['tier_surcharge'];
            $tierPrice = calculate_seat_price($basePrice, $surcharge);
            $tierClass = "tier-{$tId}";
        ?>
            <div class="tier-legend-item">
                <span class="swatch seat-box <?= $tierClass; ?>" style="cursor: default;">01</span>
                <div>
                    <strong><?= sanitize($tier['tier_name']); ?></strong>
                    <span class="tier-pill-tag" style="background: <?= $surcharge > 0 ? 'rgba(245, 158, 11, 0.15)' : 'rgba(43, 45, 122, 0.1)'; ?>; color: <?= $surcharge > 0 ? '#b45309' : '#1d1f58'; ?>;">
                        <?= format_currency($tierPrice); ?>
                        <?php if ($surcharge > 0): ?>
                            <small>(+<?= format_currency($surcharge); ?>)</small>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="tier-legend-item" style="border-left: 1px solid var(--c-line, #dcdff0); padding-left: 1rem;">
            <span class="swatch seat-box" style="background: var(--c-gold, #f5b93a); color: #000; border-color: #d97706; font-weight: 800;">✓</span>
            <span>Selected</span>
        </div>

        <div class="tier-legend-item">
            <span class="swatch seat-box" style="background: #f8fafc; border-color: #cbd5e1; color: #94a3b8; text-decoration: line-through;">✕</span>
            <span style="color: var(--c-muted, #5d6180);">Reserved</span>
        </div>
    </div>

    <!-- CINEMA SCREEN CURVATURE -->
    <div class="theater-screen">FRONT CINEMA SCREEN</div>

    <!-- SEAT SELECTION FORM -->
    <form method="POST" action="<?= BASE_URL; ?>/cart_update.php" id="seatBookingForm">
        <input type="hidden" name="type" value="add_tickets">
        <input type="hidden" name="screening_id" value="<?= $screeningId; ?>">

        <!-- VISUAL GRID MATRIX WITH TIER SECTION DIVIDERS & ROW BADGES -->
        <div class="seat-grid-container">
            <?php 
            $currentZoneTierId = null;
            $zoneIcons = [1 => '🎟️', 2 => '✨', 3 => '👑'];
            $microTags = [1 => 'STD', 2 => 'CPL', 3 => 'VIP'];

            foreach ($rowsGrouped as $rowLetter => $seatsInRow): 
                $firstSeat = $seatsInRow[0] ?? null;
                $rowTierId = (int)($firstSeat['tier_id'] ?? 1);
                $rowTierName = $firstSeat['tier_name'] ?? 'Standard';
                $rowSurcharge = (float)($firstSeat['tier_surcharge'] ?? 0);
                $rowPrice = calculate_seat_price($basePrice, $rowSurcharge);
                $zoneIcon = $zoneIcons[$rowTierId] ?? '🎟️';
                $microTag = $microTags[$rowTierId] ?? 'STD';
            ?>
                <?php if ($currentZoneTierId !== $rowTierId): 
                    $currentZoneTierId = $rowTierId;
                ?>
                    <div class="tier-zone-divider zone-tier-<?= $rowTierId; ?>">
                        <div class="zone-title">
                            <span><?= $zoneIcon; ?> <?= sanitize($rowTierName); ?> Zone</span>
                        </div>
                        <div class="zone-price">
                            <?= format_currency($rowPrice); ?> / seat
                            <?php if ($rowSurcharge > 0): ?>
                                <small style="font-size: 0.78rem; font-weight: 500; opacity: 0.9;">(+<?= format_currency($rowSurcharge); ?> premium)</small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="seat-row">
                    <div class="seat-row-label-wrap">
                        <span class="seat-row-tier-pill pill-tier-<?= $rowTierId; ?>"><?= $microTag; ?></span>
                        <span class="seat-row-label"><?= sanitize($rowLetter); ?></span>
                    </div>

                    <?php foreach ($seatsInRow as $st): 
                        $seatId = (int)$st['seat_id'];
                        $isSold = in_array($seatId, $soldSeatIds, true);
                        $tId = (int)$st['tier_id'];
                        $tierName = $st['tier_name'];
                        $surcharge = (float)$st['tier_surcharge'];
                        $computedPrice = calculate_seat_price($basePrice, $surcharge);
                        $seatLabel = $st['label'];
                        $seatMicro = $microTags[$tId] ?? 'STD';
                        $tooltip = "Seat {$seatLabel} • {$tierName} (" . format_currency($computedPrice) . ")" . ($isSold ? " [Reserved]" : "");
                    ?>
                        <label class="seat-checkbox-wrap tier-<?= $tId; ?> <?= $isSold ? 'is-sold' : ''; ?>" title="<?= sanitize($tooltip); ?>">
                            <input type="checkbox" 
                                   name="seats[]" 
                                   value="<?= $seatId; ?>"
                                   class="seat-select-checkbox"
                                   data-seat-id="<?= $seatId; ?>"
                                   data-label="<?= sanitize($seatLabel); ?>"
                                   data-tier-id="<?= $tId; ?>"
                                   data-tier-name="<?= sanitize($tierName); ?>"
                                   data-tier-surcharge="<?= $surcharge; ?>"
                                   data-price="<?= $computedPrice; ?>"
                                   <?= $isSold ? 'disabled' : ''; ?>>
                            <span class="seat-box tier-<?= $tId; ?>">
                                <span class="seat-num"><?= (int)$st['seat_number']; ?></span>
                                <span class="seat-tier-micro"><?= $seatMicro; ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>

                    <div class="seat-row-label-wrap label-right">
                        <span class="seat-row-label"><?= sanitize($rowLetter); ?></span>
                        <span class="seat-row-tier-pill pill-tier-<?= $rowTierId; ?>"><?= $microTag; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- REAL-TIME INTERACTIVE SEAT & TIER SUMMARY PANEL -->
        <div class="seat-live-summary">
            <div class="summary-details">
                <div class="summary-title">Selected Cinema Seats (<span id="selectedCount">0</span>):</div>
                <div class="selected-chips" id="selectedChipsContainer">
                    <span style="color: #64748b; font-size: 0.85rem; font-style: italic;" id="emptySelectionNote">
                        Click on available seats above to reserve.
                    </span>
                </div>
            </div>

            <div class="summary-pricing">
                <div style="font-size: 0.8rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em;">Computed Ticket Subtotal:</div>
                <div class="summary-subtotal" id="summarySubtotalDisplay">₱0.00</div>
                <div class="summary-tier-breakdown" id="summaryTierBreakdown">Base ₱<?= number_format($basePrice, 2); ?> per standard admission</div>
            </div>

            <div>
                <button type="submit" class="btn btn-primary" id="btnAddToCart" style="padding: 0.75rem 1.5rem; font-size: 0.95rem;" disabled>
                    + Add Seats to Cart
                </button>
            </div>
        </div>
    </form>
</div>

<!-- REAL-TIME SEAT TIER CALCULATION CONTROLLER (Pure Vanilla JS) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var checkboxes = document.querySelectorAll('.seat-select-checkbox');
    var chipsContainer = document.getElementById('selectedChipsContainer');
    var emptyNote = document.getElementById('emptySelectionNote');
    var countDisplay = document.getElementById('selectedCount');
    var subtotalDisplay = document.getElementById('summarySubtotalDisplay');
    var breakdownDisplay = document.getElementById('summaryTierBreakdown');
    var btnSubmit = document.getElementById('btnAddToCart');
    var basePrice = <?= json_encode($basePrice); ?>;

    function updateSeatSummary() {
        var selected = [];
        var total = 0.00;
        var tierCounts = {};

        checkboxes.forEach(function(cb) {
            if (cb.checked) {
                var seatId = cb.getAttribute('data-seat-id');
                var label = cb.getAttribute('data-label');
                var tierName = cb.getAttribute('data-tier-name');
                var price = parseFloat(cb.getAttribute('data-price')) || 0.00;

                selected.push({
                    id: seatId,
                    label: label,
                    tierName: tierName,
                    price: price
                });

                total += price;
                tierCounts[tierName] = (tierCounts[tierName] || 0) + 1;
            }
        });

        // 1. Update counter
        countDisplay.textContent = selected.length;

        // 2. Render chips
        chipsContainer.innerHTML = '';
        if (selected.length === 0) {
            chipsContainer.appendChild(emptyNote);
            btnSubmit.disabled = true;
            btnSubmit.textContent = '+ Add Seats to Cart';
            breakdownDisplay.textContent = 'Base ₱' + basePrice.toFixed(2) + ' per standard admission';
        } else {
            selected.forEach(function(s) {
                var chip = document.createElement('span');
                chip.className = 'seat-chip';
                chip.innerHTML = '<strong>' + s.label + '</strong> <span class="chip-tier">' + s.tierName + '</span> (₱' + s.price.toFixed(2) + ')';
                chipsContainer.appendChild(chip);
            });

            // 3. Render breakdown line
            var breakdownParts = [];
            for (var tName in tierCounts) {
                breakdownParts.push(tierCounts[tName] + 'x ' + tName);
            }
            breakdownDisplay.textContent = breakdownParts.join(' • ');

            // 4. Update submit button
            btnSubmit.disabled = false;
            btnSubmit.textContent = '+ Add ' + selected.length + ' Seat' + (selected.length > 1 ? 's' : '') + ' (₱' + total.toFixed(2) + ')';
        }

        // 5. Update subtotal
        subtotalDisplay.textContent = '₱' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateSeatSummary);
    });

    // Run on load in case browser restored checkbox states
    updateSeatSummary();
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
