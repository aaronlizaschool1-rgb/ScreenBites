<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/seat_pricing.php';
require_admin();

$tierId = (int)($_GET['id'] ?? 0);
if ($tierId <= 0) {
    header("Location: " . BASE_URL . "/ticketing/tiers.php");
    exit;
}

$tier = get_seat_tier_by_id($conn, $tierId);
if (!$tier) {
    set_flash('error', 'Seat tier not found.');
    header("Location: " . BASE_URL . "/ticketing/tiers.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tierName = trim($_POST['tier_name'] ?? '');
    $tierSurcharge = (float)($_POST['tier_surcharge'] ?? -1);

    if (empty($tierName)) {
        $errors[] = 'Tier category name is required.';
    } elseif (mb_strlen($tierName) > 50) {
        $errors[] = 'Tier category name cannot exceed 50 characters.';
    }

    if ($tierSurcharge < 0.00) {
        $errors[] = 'Tier surcharge must be a positive number or 0.00.';
    }

    if (empty($errors)) {
        $sql = "UPDATE seat_tiers SET tier_name = ?, tier_surcharge = ? WHERE tier_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sdi", $tierName, $tierSurcharge, $tierId);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                audit_log(
                    'UPDATE_SEAT_TIER',
                    'seat_tiers',
                    $tierId,
                    "Updated tier #{$tierId} ({$tierName}) surcharge from ₱" . number_format($tier['tier_surcharge'], 2) . " to ₱" . number_format($tierSurcharge, 2)
                );

                set_flash('success', "Seat tier '{$tierName}' successfully updated to " . format_currency($tierSurcharge) . " surcharge.");
                header("Location: " . BASE_URL . "/ticketing/tiers.php");
                exit;
            } else {
                $errors[] = 'Failed to update seat tier: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$pageTitle = 'Edit Seat Tier - ' . $tier['tier_name'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sb-page-header">
    <div>
        <h1>Edit Seat Tier Pricing</h1>
        <p>Update category name or surcharge premium for <strong><?= sanitize($tier['tier_name']); ?></strong>.</p>
    </div>
    <a href="<?= BASE_URL; ?>/ticketing/tiers.php" class="btn btn-secondary">&larr; Back to Tiers</a>
</div>

<div class="sb-card" style="max-width: 580px; margin: 0 auto; padding: 2rem;">
    <?php if (!empty($errors)): ?>
        <div class="sb-alert sb-alert-danger">
            <ul style="margin: 0; padding-left: 1.25rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= sanitize($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL; ?>/ticketing/edit_tier.php?id=<?= $tierId; ?>">
        <div class="form-group">
            <label for="tier_name">Seat Tier Name *</label>
            <input type="text"
                   id="tier_name"
                   name="tier_name"
                   value="<?= sanitize($_POST['tier_name'] ?? $tier['tier_name']); ?>"
                   class="form-control"
                   required
                   placeholder="e.g. Deluxe Couple Seats">
        </div>

        <div class="form-group">
            <label for="tier_surcharge">Tier Surcharge / Premium Amount (PHP) *</label>
            <input type="number"
                   step="0.01"
                   min="0"
                   id="tier_surcharge"
                   name="tier_surcharge"
                   value="<?= htmlspecialchars(number_format((float)($_POST['tier_surcharge'] ?? $tier['tier_surcharge']), 2, '.', '')); ?>"
                   class="form-control"
                   required
                   placeholder="0.00">
            <small style="color: var(--c-muted, #5d6180); display: block; margin-top: 0.35rem;">
                Set to <code>0.00</code> for standard seats. Any amount greater than 0 will be added dynamically to screening base prices.
            </small>
        </div>

        <div style="background: #f8fafc; border: 1px solid var(--c-line, #dcdff0); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
            <div style="font-size: 0.82rem; font-weight: 700; color: var(--c-muted, #5d6180); text-transform: uppercase;">Real-Time Calculation Example</div>
            <div style="font-size: 0.95rem; margin-top: 0.35rem; color: var(--c-ink, #1d1f3a);">
                If a movie screening base price is <strong>₱320.00</strong>, a seat in this tier will cost:
                <div style="margin-top: 0.4rem; font-size: 1.25rem; font-weight: 800; color: var(--c-indigo, #2b2d7a); font-family: 'Bricolage Grotesque', sans-serif;">
                    ₱320.00 + <span id="previewSurcharge">₱<?= number_format($tier['tier_surcharge'], 2); ?></span> = <span id="previewTotal">₱<?= number_format(320.00 + $tier['tier_surcharge'], 2); ?></span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">
                Save Changes &rarr;
            </button>
            <a href="<?= BASE_URL; ?>/ticketing/tiers.php" class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.getElementById('tier_surcharge').addEventListener('input', function() {
    var val = parseFloat(this.value) || 0.00;
    if (val < 0) val = 0.00;
    var base = 320.00;
    var total = base + val;
    document.getElementById('previewSurcharge').textContent = '₱' + val.toFixed(2);
    document.getElementById('previewTotal').textContent = '₱' + total.toFixed(2);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
