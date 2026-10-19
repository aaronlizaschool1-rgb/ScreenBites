<?php
use Core\Auth;

$user = Auth::user();
$kpis = $kpis ?? []; // optional: [['label' => 'Tickets today', 'value' => '12'], ...]
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($user['full_name']); ?>.</p>
    </div>
</div>

<?php if ($kpis): ?>
<div class="kpi-grid">
    <?php foreach ($kpis as $k): ?>
        <div class="kpi">
            <p class="kpi-label"><?= htmlspecialchars((string) $k['label']); ?></p>
            <p class="kpi-value"><?= htmlspecialchars((string) $k['value']); ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="link-grid">
    <a class="link-card" href="<?= BASE_URL; ?>/reports/financial">
        <strong>Net income report</strong>
        <span>Revenue, expenses and profit margin by month.</span>
    </a>
    <a class="link-card" href="<?= BASE_URL; ?>/reports/sales">
        <strong>Sales report</strong>
        <span>Box office compared with concessions.</span>
    </a>
    <a class="link-card" href="<?= BASE_URL; ?>/reports/occupancy">
        <strong>Occupancy analytics</strong>
        <span>Seats sold per screening and peak hours.</span>
    </a>
    <a class="link-card" href="<?= BASE_URL; ?>/audit">
        <strong>Audit logs</strong>
        <span>Cancellations, refunds, overrides and failed sign-ins.</span>
    </a>
</div>