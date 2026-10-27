<?php
/**
 * ScreenBites - Log Utility Expense Bill
 * Procedural module modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Log Utility Bill';
require_once dirname(__DIR__) . '/includes/header.php';

$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');
?>

<div class="sb-page-header">
    <div>
        <h1>Log Monthly Utility Bill</h1>
        <p>Record electricity or water utility overhead for operational auditing.</p>
    </div>
    <a href="<?= BASE_URL; ?>/utilities/index.php" class="btn btn-secondary">&larr; Back to Utilities</a>
</div>

<div class="sb-card" style="max-width: 650px; margin: 0 auto;">
    <form method="POST" action="<?= BASE_URL; ?>/utilities/store.php">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="utility_type">Utility Type *</label>
                <select id="utility_type" name="utility_type" class="form-control" required>
                    <option value="Electricity">⚡ Electricity (HVAC, Projectors & Audio)</option>
                    <option value="Water">💧 Water (Restrooms & Concessions)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="bill_amount">Bill Amount (PHP) *</label>
                <input type="number" step="0.01" id="bill_amount" name="bill_amount" class="form-control" min="0.01" required placeholder="e.g. 38500.00">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="billing_month">Billing Month *</label>
                <select id="billing_month" name="billing_month" class="form-control" required>
                    <?php
                    $months = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                    foreach ($months as $mNum => $mName):
                    ?>
                        <option value="<?= $mNum; ?>" <?= $currentMonth === $mNum ? 'selected' : ''; ?>>
                            <?= $mName; ?> (<?= $mNum; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="billing_year">Billing Year *</label>
                <input type="number" id="billing_year" name="billing_year" class="form-control" min="2020" max="2035" value="<?= $currentYear; ?>" required>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="payment_status">Payment Status *</label>
                <select id="payment_status" name="payment_status" class="form-control" required>
                    <option value="Paid" selected>Paid</option>
                    <option value="Unpaid">Unpaid</option>
                </select>
            </div>

            <div class="form-group">
                <label for="payment_date">Payment / Settlement Date</label>
                <input type="date" id="payment_date" name="payment_date" value="<?= date('Y-m-d'); ?>" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label for="due_date">Due Date (Optional if Paid)</label>
            <input type="date" id="due_date" name="due_date" class="form-control">
        </div>

        <div class="form-group">
            <label for="notes">Notes / Provider Reference</label>
            <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="e.g. Meralco Account #123456 - Cinema main line and projector bank"></textarea>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= BASE_URL; ?>/utilities/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Utility Bill</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
