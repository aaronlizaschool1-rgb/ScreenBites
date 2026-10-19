<?php
use Core\Auth;

$user = Auth::user();
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($user['full_name']); ?>.</p>
    </div>
</div>

<div class="card">
    <p>KPI cards and charts will go here once the dashboard data contract is confirmed with Aaron.</p>
    <p>
        <?php if (Auth::hasRole(ROLE_ADMIN)): ?>
            <a class="btn" href="<?= BASE_URL; ?>/audit">Open audit logs</a>
        <?php endif; ?>
        <?php if (Auth::hasRole([ROLE_ADMIN, ROLE_CASHIER, ROLE_TICKETING])): ?>
            <a class="btn btn-ghost" href="<?= BASE_URL; ?>/pos">Open snack bar POS</a>
        <?php endif; ?>
    </p>
</div>