<?php
/**
 * ScreenBites - Screening Schedules
 * Modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Screening Schedules';
require_once dirname(__DIR__) . '/includes/header.php';

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedDateEsc = mysqli_real_escape_string($conn, $selectedDate);

$sql = "SELECT s.screening_id, s.screening_date, s.start_time, s.end_time, s.base_price, 
               m.movie_id, m.title AS movie_title, m.duration_minutes, m.rating,
               h.hall_id, h.hall_name
        FROM screenings s
        INNER JOIN movies m ON s.movie_id = m.movie_id
        INNER JOIN halls h ON s.hall_id = h.hall_id
        WHERE s.screening_date = '{$selectedDateEsc}'
        ORDER BY h.hall_name ASC, s.start_time ASC";

$res = mysqli_query($conn, $sql);
?>

<div class="sb-page-header">
    <div>
        <h1>Cinema Screening Schedules</h1>
        <p>Showtimes and hall allocations for <?= date('l, F j, Y', strtotime($selectedDate)); ?></p>
    </div>
    <div>
        <?php if (is_admin()): ?>
            <a href="<?= BASE_URL; ?>/schedules/create.php" class="btn btn-primary">+ Add New Screening</a>
        <?php endif; ?>
    </div>
</div>

<div class="sb-card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
    <form method="GET" action="<?= BASE_URL; ?>/schedules/index.php" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <label for="date" style="font-weight: 600; font-size: 0.9rem; color: var(--text-muted);">Select Date:</label>
        <input type="date" id="date" name="date" value="<?= sanitize($selectedDate); ?>" class="form-control" style="width: auto;">
        <button type="submit" class="btn btn-secondary btn-sm">Filter Date</button>
        <?php if ($selectedDate !== date('Y-m-d')): ?>
            <a href="<?= BASE_URL; ?>/schedules/index.php" class="btn btn-sm btn-secondary">Today</a>
        <?php endif; ?>
    </form>
</div>

<div class="sb-card">
    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th>Movie</th>
                    <th>Cinema Hall</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Ticket Price</th>
                    <th style="text-align: right; width: 200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res && mysqli_num_rows($res) > 0): ?>
                    <?php while ($sc = mysqli_fetch_assoc($res)): 
                        $scId = (int)$sc['screening_id'];
                    ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($sc['movie_title']); ?></strong>
                                <small style="display: block; color: var(--text-muted);">
                                    <?= sanitize($sc['rating'] ?? 'PG'); ?> &bull; <?= (int)$sc['duration_minutes']; ?> mins
                                </small>
                            </td>
                            <td><?= sanitize($sc['hall_name']); ?></td>
                            <td><strong style="color: #ffffff;"><?= date('g:i A', strtotime($sc['start_time'])); ?></strong></td>
                            <td><span style="color: var(--text-muted);"><?= date('g:i A', strtotime($sc['end_time'])); ?></span></td>
                            <td><strong style="color: var(--accent-gold);"><?= format_currency($sc['base_price']); ?></strong></td>
                            <td style="text-align: right;">
                                <a href="<?= BASE_URL; ?>/ticketing/seat_matrix.php?screening_id=<?= $scId; ?>" class="btn btn-sm btn-primary">
                                    Book Seats &rarr;
                                </a>
                                <?php if (is_admin()): ?>
                                    <a href="<?= BASE_URL; ?>/schedules/delete.php?id=<?= $scId; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Delete this screening schedule?');">
                                        Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            No screenings scheduled for this date.
                            <?php if (is_admin()): ?>
                                Click "+ Add New Screening" to schedule a movie.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
