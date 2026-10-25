<?php
/**
 * ScreenBites: Utility Overhead Expense Logging View (FR11, FR12, FR13)
 * 
 * Historical ledger and entry interface for cinema operational utilities:
 * Electric bills (projectors, HVAC, sound) and Water bills (restrooms, concessions).
 * Strictly restricted to Administrators (FR13).
 * 
 * @var array<int, array<string, mixed>> $expenses
 * @var int                              $selectedYear
 * @var string|null                      $selectedType
 * @var array<int, string>               $utilityTypes
 * @var string                           $csrfToken
 * @var string|null                      $flashSuccess
 * @var string|null                      $flashError
 */

$e = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$peso = "\u{20B1}";
$fmt = fn($n): string => number_format((float)$n, 2);

$expenses     = is_array($expenses ?? null) ? $expenses : [];
$selectedYear = (int)($selectedYear ?? date('Y'));
$selectedType = !empty($selectedType) ? (string)$selectedType : null;
$utilityTypes = is_array($utilityTypes ?? null) ? $utilityTypes : ['Electricity', 'Water'];

// Calculate ledger aggregates
$totalElectricity = 0.00;
$totalWater       = 0.00;
$invoiceCount     = count($expenses);

foreach ($expenses as $item) {
    $amt = (float)$item['bill_amount'];
    if ($item['utility_type'] === 'Electricity') {
        $totalElectricity += $amt;
    } elseif ($item['utility_type'] === 'Water') {
        $totalWater += $amt;
    }
}
$grandTotal = $totalElectricity + $totalWater;

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<div class="page-head">
    <div>
        <h1>Operational Utility Overhead</h1>
        <p>Log and audit monthly electric and water expenses for Net Operating Profit computation (FR11, FR12).</p>
    </div>
    <div class="utility-head-actions">
        <button type="button" class="btn" id="openExpenseModalBtn">
            + Log Utility Bill
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Total Utilities (<?= $selectedYear; ?>)</p>
        <p class="kpi-value"><?= $peso . $fmt($grandTotal); ?></p>
        <p class="kpi-note">Overhead deducted from gross income</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Electricity Overhead</p>
        <p class="kpi-value text-electricity"><?= $peso . $fmt($totalElectricity); ?></p>
        <p class="kpi-note">Projectors, HVAC &amp; audio draw</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Water Utility Overhead</p>
        <p class="kpi-value text-water"><?= $peso . $fmt($totalWater); ?></p>
        <p class="kpi-note">Restrooms &amp; concessions cleaning</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Logged Invoices</p>
        <p class="kpi-value"><?= $invoiceCount; ?></p>
        <p class="kpi-note">Monthly statements recorded</p>
    </div>
</div>

<!-- Filter Toolbar -->
<section class="card" style="margin-bottom: 1.25rem;">
    <form method="GET" action="<?= BASE_URL; ?>/expenses/utilities" class="report-filters" style="margin-bottom: 0; padding: 0; border: none;">
        <div class="field">
            <label for="filterYear">Billing Year</label>
            <select id="filterYear" name="year" onchange="this.form.submit()">
                <?php 
                $currentYear = (int)date('Y');
                for ($y = $currentYear + 1; $y >= 2022; $y--): 
                ?>
                    <option value="<?= $y; ?>" <?= $y === $selectedYear ? 'selected' : ''; ?>>
                        <?= $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="field">
            <label for="filterType">Utility Classification</label>
            <select id="filterType" name="type" onchange="this.form.submit()">
                <option value="">All Utility Types</option>
                <?php foreach ($utilityTypes as $type): ?>
                    <option value="<?= $e($type); ?>" <?= $type === $selectedType ? 'selected' : ''; ?>>
                        <?= $e($type); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-ghost" style="padding: .5rem 1rem;">Filter</button>
            <?php if (!empty($selectedType) || $selectedYear !== (int)date('Y')): ?>
                <a href="<?= BASE_URL; ?>/expenses/utilities" class="btn btn-ghost" style="padding: .5rem .75rem;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- Utility Expense Ledger Table (FR11) -->
<section class="card" aria-label="Utility Expense Ledger">
    <div class="table-scroll">
        <table class="ledger utility-table" id="utilityLedgerTable">
            <thead>
                <tr>
                    <th style="min-width: 140px;">Billing Period</th>
                    <th style="min-width: 150px;">Utility Type</th>
                    <th class="num" style="min-width: 130px;">Invoice Amount</th>
                    <th style="min-width: 120px;">Payment Date</th>
                    <th>Operational Notes / Equipment Allocation</th>
                    <th style="min-width: 120px;">Recorded By</th>
                    <th style="text-align: right; min-width: 100px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="7" class="empty-note">
                            No utility bills recorded for <?= $selectedYear; ?> <?= $selectedType ? "({$selectedType})" : ''; ?>.<br>
                            Click "+ Log Utility Bill" to record a monthly electricity or water statement.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $row): 
                        $eId    = (int)$row['expense_id'];
                        $type   = (string)$row['utility_type'];
                        $month  = (int)$row['billing_month'];
                        $year   = (int)$row['billing_year'];
                        $amount = (float)$row['bill_amount'];
                        $mName  = $monthNames[$month] ?? "Month {$month}";
                        $isElec = ($type === 'Electricity');
                    ?>
                        <tr>
                            <td>
                                <strong><?= $mName . ' ' . $year; ?></strong>
                            </td>
                            <td>
                                <?php if ($isElec): ?>
                                    <span class="utility-badge badge-elec">
                                        <svg class="utility-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                                        </svg>
                                        <span>Electricity</span>
                                    </span>
                                <?php else: ?>
                                    <span class="utility-badge badge-water">
                                        <svg class="utility-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                                        </svg>
                                        <span>Water</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="num">
                                <strong class="utility-amount"><?= $peso . $fmt($amount); ?></strong>
                            </td>
                            <td>
                                <?= !empty($row['payment_date']) ? date('M d, Y', strtotime($row['payment_date'])) : '&mdash;'; ?>
                            </td>
                            <td class="utility-notes-cell">
                                <?= !empty($row['notes']) ? $e($row['notes']) : '<span style="color:var(--c-muted);">&mdash;</span>'; ?>
                            </td>
                            <td>
                                <small><?= $e($row['recorded_by_name'] ?? 'Admin'); ?></small>
                            </td>
                            <td style="text-align: right;">
                                <button type="button" 
                                        class="btn btn-sm btn-ghost btn-edit-expense"
                                        data-type="<?= $e($type); ?>"
                                        data-month="<?= $month; ?>"
                                        data-year="<?= $year; ?>"
                                        data-amount="<?= $amount; ?>"
                                        data-date="<?= $e($row['payment_date'] ?? date('Y-m-d')); ?>"
                                        data-notes="<?= $e($row['notes'] ?? ''); ?>"
                                        title="Modify recorded bill amount or notes (FR16)">
                                    Edit Bill
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Modal: Log / Update Utility Expense Entry (FR11, FR16) -->
<dialog class="sched-dialog" id="utilityExpenseModal" aria-labelledby="modalExpenseTitle">
    <form method="POST" action="<?= BASE_URL; ?>/expenses/utilities/store" id="expenseEntryForm">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken); ?>">

        <h2 id="modalExpenseTitle">Log Utility Overhead Expense</h2>
        <p class="sched-note">Record monthly overhead costs. Updating an existing period replaces previous amounts and logs an audit trail (FR11, FR16).</p>

        <div class="sched-row">
            <div class="sched-field">
                <label for="modalUtilityType">Utility Type *</label>
                <select id="modalUtilityType" name="utility_type" required>
                    <option value="Electricity">&#9889; Electricity (Projector, HVAC, Audio)</option>
                    <option value="Water">&#128167; Water (Restrooms, Concessions Cleaning)</option>
                </select>
            </div>
            <div class="sched-field">
                <label for="modalBillingYear">Billing Year *</label>
                <input type="number" 
                       id="modalBillingYear" 
                       name="billing_year" 
                       min="2020" 
                       max="2035" 
                       value="<?= $selectedYear; ?>" 
                       required>
            </div>
        </div>

        <div class="sched-row">
            <div class="sched-field">
                <label for="modalBillingMonth">Billing Month *</label>
                <select id="modalBillingMonth" name="billing_month" required>
                    <?php 
                    $curMonth = (int)date('n');
                    foreach ($monthNames as $mNum => $mLabel): 
                    ?>
                        <option value="<?= $mNum; ?>" <?= $mNum === $curMonth ? 'selected' : ''; ?>>
                            <?= $mLabel; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sched-field">
                <label for="modalBillAmount">Bill Amount (<?= $peso; ?>) *</label>
                <input type="number" 
                       id="modalBillAmount" 
                       name="bill_amount" 
                       step="0.01" 
                       min="0.01" 
                       required 
                       placeholder="e.g. 24500.00">
            </div>
        </div>

        <div class="sched-field">
            <label for="modalPaymentDate">Payment / Settlement Date *</label>
            <input type="date" 
                   id="modalPaymentDate" 
                   name="payment_date" 
                   value="<?= date('Y-m-d'); ?>" 
                   required>
        </div>

        <div class="sched-field">
            <label for="modalNotes">Operational Allocation Notes</label>
            <textarea id="modalNotes" 
                      name="notes" 
                      rows="3" 
                      placeholder="e.g. Meralco Cinema 1 &amp; 2 Laser Projectors and Central HVAC draw"></textarea>
        </div>

        <div class="sched-actions">
            <button type="button" class="btn btn-ghost" id="closeExpenseModalBtn">Cancel</button>
            <button type="submit" class="btn" id="modalSubmitBtn">Record Expense</button>
        </div>
    </form>
</dialog>

<script>
(function() {
    'use strict';

    var modal       = document.getElementById('utilityExpenseModal');
    var openBtn     = document.getElementById('openExpenseModalBtn');
    var closeBtn    = document.getElementById('closeExpenseModalBtn');
    var modalTitle  = document.getElementById('modalExpenseTitle');
    var submitBtn   = document.getElementById('modalSubmitBtn');

    var typeSelect  = document.getElementById('modalUtilityType');
    var monthSelect = document.getElementById('modalBillingMonth');
    var yearInput   = document.getElementById('modalBillingYear');
    var amountInput = document.getElementById('modalBillAmount');
    var dateInput   = document.getElementById('modalPaymentDate');
    var notesInput  = document.getElementById('modalNotes');

    var editBtns    = document.querySelectorAll('.btn-edit-expense');

    function resetModal() {
        if (modalTitle) modalTitle.textContent = 'Log Utility Overhead Expense';
        if (submitBtn) submitBtn.textContent = 'Record Expense';
        if (amountInput) amountInput.value = '';
        if (notesInput) notesInput.value = '';
        if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
    }

    if (openBtn && modal) {
        openBtn.addEventListener('click', function() {
            resetModal();
            if (typeof modal.showModal === 'function') {
                modal.showModal();
            }
        });
    }

    if (closeBtn && modal) {
        closeBtn.addEventListener('click', function() {
            modal.close();
        });
    }

    // Pre-populate modal for editing existing expense record
    editBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type   = btn.getAttribute('data-type');
            var month  = btn.getAttribute('data-month');
            var year   = btn.getAttribute('data-year');
            var amount = btn.getAttribute('data-amount');
            var date   = btn.getAttribute('data-date');
            var notes  = btn.getAttribute('data-notes');

            if (modalTitle) modalTitle.textContent = 'Update ' + type + ' Expense (' + month + '/' + year + ')';
            if (submitBtn) submitBtn.textContent = 'Save Expense Modification';

            if (typeSelect) typeSelect.value = type;
            if (monthSelect) monthSelect.value = month;
            if (yearInput) yearInput.value = year;
            if (amountInput) amountInput.value = amount;
            if (dateInput && date) dateInput.value = date;
            if (notesInput) notesInput.value = notes || '';

            if (modal && typeof modal.showModal === 'function') {
                modal.showModal();
            }
        });
    });
})();
</script>
