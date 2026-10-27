<?php
/**
 * ScreenBites - Visual Seat Selection Matrix
 * Checkbox-driven cinema seating grid adding directly to session cart
 */

require_once dirname(__DIR__) . '/includes/config.php';

$screeningId = (int)($_GET['screening_id'] ?? 0);

if ($screeningId <= 0) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// Fetch screening details
$sql = "SELECT s.screening_id, s.screening_date, s.start_time, s.base_price, 
               m.movie_id, m.title AS movie_title, m.poster_path, m.duration_minutes, m.rating,
               h.hall_id, h.hall_name
        FROM screenings s
        INNER JOIN movies m ON s.movie_id = m.movie_id
        INNER JOIN halls h ON s.hall_id = h.hall_id
        WHERE s.screening_id = {$screeningId} LIMIT 1";

$res = mysqli_query($conn, $sql);
$screening = mysqli_fetch_assoc($res);

if (!$screening) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$hallId = (int)$screening['hall_id'];

// Fetch already-sold seats for this screening
$soldRes = mysqli_query($conn, "SELECT seat_id FROM tickets WHERE screening_id = {$screeningId} AND ticket_status = 'Valid'");
$soldSeatIds = [];
while ($soldRow = mysqli_fetch_assoc($soldRes)) {
    $soldSeatIds[] = (int)$soldRow['seat_id'];
}

// Fetch all seats in this cinema hall
$seatsRes = mysqli_query($conn, "SELECT seat_id, seat_row, seat_number FROM seats WHERE hall_id = {$hallId} ORDER BY seat_row ASC, seat_number ASC");
$rowsGrouped = [];
while ($seat = mysqli_fetch_assoc($seatsRes)) {
    $r = $seat['seat_row'];
    $rowsGrouped[$r][] = $seat;
}

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
    <div>
        <a href="<?= BASE_URL; ?>/index.php" class="btn btn-secondary">&larr; Back to Movies</a>
        <a href="<?= BASE_URL; ?>/view_cart.php" class="btn btn-secondary">View Cart</a>
    </div>
</div>

<div class="sb-card" style="text-align: center;">
    <div style="display: flex; justify-content: center; gap: 2rem; margin-bottom: 1.5rem; font-size: 0.9rem;">
        <div style="display: flex; align-items: center; gap: 0.4rem;">
            <span class="seat-box" style="cursor: default;">01</span> Available
        </div>
        <div style="display: flex; align-items: center; gap: 0.4rem;">
            <span class="seat-box" style="background: var(--accent-gold); color: #000; border-color: var(--accent-gold); cursor: default; font-weight: 700;">02</span> Selected
        </div>
        <div style="display: flex; align-items: center; gap: 0.4rem;">
            <span class="seat-box" style="background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.4); color: var(--accent-red); cursor: not-allowed; text-decoration: line-through;">03</span> Reserved / Sold
        </div>
    </div>

    <div class="theater-screen">FRONT CINEMA SCREEN</div>

    <form method="POST" action="<?= BASE_URL; ?>/cart_update.php">
        <input type="hidden" name="type" value="add_tickets">
        <input type="hidden" name="screening_id" value="<?= $screeningId; ?>">

        <div class="seat-grid-container">
            <?php foreach ($rowsGrouped as $rowLetter => $seatsInRow): ?>
                <div class="seat-row">
                    <span class="seat-row-label"><?= sanitize($rowLetter); ?></span>
                    <?php foreach ($seatsInRow as $st): 
                        $seatId = (int)$st['seat_id'];
                        $isSold = in_array($seatId, $soldSeatIds, true);
                    ?>
                        <label class="seat-checkbox-wrap <?= $isSold ? 'is-sold' : ''; ?>" title="Seat <?= sanitize($rowLetter . $st['seat_number']); ?><?= $isSold ? ' (Sold)' : ''; ?>">
                            <input type="checkbox" name="seats[]" value="<?= $seatId; ?>" <?= $isSold ? 'disabled' : ''; ?>>
                            <span class="seat-box"><?= (int)$st['seat_number']; ?></span>
                        </label>
                    <?php endforeach; ?>
                    <span class="seat-row-label"><?= sanitize($rowLetter); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="border-top: 1px solid var(--card-border); padding-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; max-width: 500px; margin: 0 auto; flex-wrap: wrap; gap: 1rem;">
            <div style="text-align: left;">
                <span style="color: var(--text-muted); font-size: 0.9rem;">Price per ticket:</span><br>
                <strong style="font-size: 1.25rem; color: var(--accent-gold);"><?= format_currency($screening['base_price']); ?></strong>
            </div>

            <button type="submit" class="btn btn-primary">
                + Add Selected Seats to Cart
            </button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
