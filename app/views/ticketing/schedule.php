<?php
/**
 * app/views/ticketing/schedule.php  (FR1)
 *
 * @var array  $screenings
 * @var array  $movies
 * @var array  $halls
 * @var string $selectedDate
 * @var string $csrfToken
 * @var string $userRole
 *
 * Flash messages ($flashSuccess / $flashError) are already shown by layouts/sidebar.php.
 */
use Core\Auth;

$e    = fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$peso = "\u{20B1}";

$screenings = is_array($screenings ?? null) ? $screenings : [];
$movies     = is_array($movies ?? null) ? $movies : [];
$halls      = is_array($halls ?? null) ? $halls : [];

$selectedDate = (string) ($selectedDate ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || !strtotime($selectedDate)) {
    $selectedDate = date('Y-m-d');
}
$prevDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($selectedDate . ' +1 day'));
$isAdmin  = Auth::hasRole(ROLE_ADMIN);

$timeText = fn($t): string => $t ? date('g:i A', strtotime((string) $t)) : '';
$duration = function ($mins): string {
    $mins = (int) $mins;
    return $mins >= 60 ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm' : $mins . 'm';
};

// Hall capacity (rows x columns), matched by hall name
$capacity = [];
foreach ($halls as $h) {
    $capacity[(string) $h['hall_name']] = (int) $h['total_rows'] * (int) $h['total_columns'];
}

// Group screenings by hall, earliest first
$byHall = [];
foreach ($screenings as $s) {
    $byHall[(string) $s['hall_name']][] = $s;
}
ksort($byHall, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($byHall as $hallName => $list) {
    usort($list, fn($a, $b) => strcmp((string) $a['start_time'], (string) $b['start_time']));
    $byHall[$hallName] = $list;
}
?>
<div class="page-head">
    <div>
        <h1>Screening schedules</h1>
        <p><?= $e(date('l, F j, Y', strtotime($selectedDate))); ?> &middot; <?= count($screenings); ?> <?= count($screenings) === 1 ? 'screening' : 'screenings'; ?></p>
    </div>
</div>

<form class="report-filters" method="GET" action="<?= BASE_URL; ?>/schedules" id="dateForm">
    <a class="btn btn-ghost" href="<?= BASE_URL; ?>/schedules?date=<?= $e($prevDate); ?>">&larr; Previous day</a>
    <div class="field">
        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="<?= $e($selectedDate); ?>">
    </div>
    <a class="btn btn-ghost" href="<?= BASE_URL; ?>/schedules?date=<?= $e($nextDate); ?>">Next day &rarr;</a>
    <a class="btn btn-ghost" href="<?= BASE_URL; ?>/schedules?date=<?= date('Y-m-d'); ?>">Today</a>
    <?php if ($isAdmin): ?>
        <button type="button" class="btn sched-spacer" data-open-add>Add screening</button>
    <?php endif; ?>
</form>

<?php if (!$byHall): ?>
    <div class="card">
        <p class="empty-note">No screenings are scheduled for this date.<?= $isAdmin ? ' Use "Add screening" to create one.' : ' Try another date.'; ?></p>
    </div>
<?php endif; ?>

<?php foreach ($byHall as $hallName => $list): $cap = $capacity[$hallName] ?? 0; ?>
    <section aria-label="<?= $e($hallName); ?>">
        <h2 class="sched-hall"><?= $e($hallName); ?> <small><?= $cap ? $cap . ' seats' : ''; ?></small></h2>
        <div class="sched-list">
        <?php foreach ($list as $s):
            $sold  = (int) ($s['sold_seats_count'] ?? 0);
            $left  = $cap > 0 ? max(0, $cap - $sold) : null;
            $rate  = $cap > 0 ? min(100, round($sold / $cap * 100)) : 0;
            $tone  = $rate >= 80 ? 'is-high' : ($rate < 30 ? 'is-low' : '');
            $title = (string) $s['movie_title'];
        ?>
            <article class="sched-card">
                <div class="sched-poster" aria-hidden="true">
                    <span><?= $e(mb_strtoupper(mb_substr($title, 0, 1))); ?></span>
                    <?php if (!empty($s['poster_path'])): ?>
                        <img src="<?= BASE_URL . '/' . $e(ltrim((string) $s['poster_path'], '/')); ?>" alt="" loading="lazy" onerror="this.remove()">
                    <?php endif; ?>
                </div>
                <div class="sched-body">
                    <h3><?= $e($title); ?></h3>
                    <p class="sched-meta">
                        <?php if (!empty($s['movie_rating'])): ?><span class="rating-badge"><?= $e($s['movie_rating']); ?></span><?php endif; ?>
                        <span><?= $e($duration($s['duration_minutes'] ?? 0)); ?></span>
                        <span>Base price <?= $peso . number_format((float) $s['base_price'], 2); ?></span>
                    </p>
                    <p class="sched-time"><?= $e($timeText($s['start_time'])); ?> <span>to <?= $e($timeText($s['end_time'])); ?></span></p>

                    <?php if ($cap > 0): ?>
                        <div class="meter <?= $tone; ?>" title="<?= $sold; ?> of <?= $cap; ?> seats sold">
                            <div class="meter-bar"><span style="width: <?= $rate; ?>%"></span></div>
                            <span class="sched-left"><?= $left === 0 ? 'Sold out' : $left . ' left'; ?></span>
                        </div>
                    <?php else: ?>
                        <p class="sched-meta"><?= $sold; ?> seats sold</p>
                    <?php endif; ?>

                    <a class="btn" href="<?= BASE_URL; ?>/ticketing/screening/<?= (int) $s['screening_id']; ?>">Open Seat Matrix</a>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php if ($isAdmin): ?>
<dialog class="sched-dialog" id="addDialog" aria-labelledby="addTitle">
    <form method="POST" action="<?= BASE_URL; ?>/schedules/create" id="addForm">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? ''); ?>">
        <h2 id="addTitle">Add screening</h2>

        <?php if (!$movies || !$halls): ?>
            <p class="empty-note">You need at least one active movie and one hall before adding a screening.</p>
            <div class="sched-actions"><button type="button" class="btn btn-ghost" data-close-add>Close</button></div>
        <?php else: ?>
            <div class="sched-field">
                <label for="movie_id">Movie</label>
                <select id="movie_id" name="movie_id" required>
                    <?php foreach ($movies as $m): ?>
                        <option value="<?= (int) $m['movie_id']; ?>" data-duration="<?= (int) $m['duration_minutes']; ?>">
                            <?= $e($m['title']); ?> (<?= $e($m['rating']); ?>, <?= $e($duration($m['duration_minutes'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sched-field">
                <label for="hall_id">Hall</label>
                <select id="hall_id" name="hall_id" required>
                    <?php foreach ($halls as $h): ?>
                        <option value="<?= (int) $h['hall_id']; ?>"><?= $e($h['hall_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sched-row">
                <div class="sched-field">
                    <label for="screening_date">Date</label>
                    <input type="date" id="screening_date" name="screening_date" value="<?= $e($selectedDate); ?>" required>
                </div>
                <div class="sched-field">
                    <label for="start_time">Start time</label>
                    <input type="time" id="start_time" name="start_time" required>
                </div>
            </div>
            <div class="sched-row">
                <div class="sched-field">
                    <label for="end_time">End time (automatic)</label>
                    <input type="time" id="end_time" name="end_time" readonly tabindex="-1">
                </div>
                <div class="sched-field">
                    <label for="base_price">Base price (<?= $peso; ?>)</label>
                    <input type="number" id="base_price" name="base_price" min="0" step="0.01" required>
                </div>
            </div>
            <p class="sched-note" id="endNote" role="status"></p>
            <div class="sched-actions">
                <button type="button" class="btn btn-ghost" data-close-add>Cancel</button>
                <button type="submit" class="btn" id="addSubmit">Create screening</button>
            </div>
        <?php endif; ?>
    </form>
</dialog>
<?php endif; ?>

<script>
(function () {
  'use strict';
  var dateInput = document.getElementById('date');
  if (dateInput) {
    dateInput.addEventListener('change', function () { if (dateInput.value) { document.getElementById('dateForm').submit(); } });
  }

  var dlg = document.getElementById('addDialog');
  if (!dlg) { return; }
  var movie = document.getElementById('movie_id');
  var start = document.getElementById('start_time');
  var end = document.getElementById('end_time');
  var note = document.getElementById('endNote');
  var submit = document.getElementById('addSubmit');

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  function updateEnd() {
    if (!movie || !start || !end) { return; }
    var dur = parseInt(movie.options[movie.selectedIndex].getAttribute('data-duration'), 10);
    var parts = start.value.split(':');
    if (!dur || parts.length < 2) { end.value = ''; note.textContent = ''; submit.disabled = false; return; }
    var total = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10) + dur;
    if (total >= 1440) {
      end.value = '';
      note.textContent = 'This movie would run past midnight. Choose an earlier start time.';
      submit.disabled = true;
      return;
    }
    var h = Math.floor(total / 60), m = total % 60;
    end.value = pad(h) + ':' + pad(m);
    note.textContent = 'Ends at ' + ((h % 12) || 12) + ':' + pad(m) + ' ' + (h >= 12 ? 'PM' : 'AM') + '.';
    submit.disabled = false;
  }

  if (movie) { movie.addEventListener('change', updateEnd); }
  if (start) { start.addEventListener('input', updateEnd); }

  Array.prototype.forEach.call(document.querySelectorAll('[data-open-add]'), function (b) {
    b.addEventListener('click', function () { dlg.showModal(); updateEnd(); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-close-add]'), function (b) {
    b.addEventListener('click', function () { dlg.close(); });
  });
  dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
})();
</script>