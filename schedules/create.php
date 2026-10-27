<?php
/**
 * ScreenBites - Create Screening Schedule
 * Modeled after itim211-2b-shop/item/create.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Add New Screening Schedule';
require_once dirname(__DIR__) . '/includes/header.php';

$moviesRes = mysqli_query($conn, "SELECT movie_id, title, duration_minutes FROM movies WHERE is_active = 1 ORDER BY title ASC");
$hallsRes  = mysqli_query($conn, "SELECT hall_id, hall_name FROM halls ORDER BY hall_id ASC");
?>

<div class="sb-page-header">
    <div>
        <h1>Add New Screening Schedule</h1>
        <p>Assign a movie title to a theater hall and time slot.</p>
    </div>
    <a href="<?= BASE_URL; ?>/schedules/index.php" class="btn btn-secondary">&larr; Back to Schedules</a>
</div>

<div class="sb-card" style="max-width: 650px; margin: 0 auto;">
    <form method="POST" action="<?= BASE_URL; ?>/schedules/store.php">
        <div class="form-group">
            <label for="movie_id">Select Movie *</label>
            <select id="movie_id" name="movie_id" class="form-control" required>
                <option value="">-- Choose Movie --</option>
                <?php while ($m = mysqli_fetch_assoc($moviesRes)): ?>
                    <option value="<?= (int)$m['movie_id']; ?>">
                        <?= sanitize($m['title']); ?> (<?= (int)$m['duration_minutes']; ?> mins)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="hall_id">Cinema Hall *</label>
            <select id="hall_id" name="hall_id" class="form-control" required>
                <option value="">-- Choose Hall --</option>
                <?php while ($h = mysqli_fetch_assoc($hallsRes)): ?>
                    <option value="<?= (int)$h['hall_id']; ?>">
                        <?= sanitize($h['hall_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="screening_date">Screening Date *</label>
                <input type="date" id="screening_date" name="screening_date" value="<?= date('Y-m-d'); ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="start_time">Start Time *</label>
                <input type="time" id="start_time" name="start_time" value="13:00" class="form-control" required>
            </div>
        </div>

        <div class="form-group">
            <label for="price">Ticket Admission Price (PHP) *</label>
            <input type="number" step="0.01" min="1" id="price" name="price" value="350.00" class="form-control" required>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= BASE_URL; ?>/schedules/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Schedule Screening</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
