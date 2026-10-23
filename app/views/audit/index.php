<?php
/**
 * app/views/audit/index.php  (FR16)
 * Security Operations Audit Trail
 *
 * @var array<int, array<string, mixed>> $logs
 * @var array<int, array<string, mixed>> $users
 * @var array<int, string>               $actionTypes
 * @var array<string, mixed>             $filters
 * @var int                              $page
 * @var int                              $totalPages
 * @var int                              $totalLogs
 */

$filters     = $filters ?? [];
$f           = fn(string $key) => $filters[$key] ?? '';
$page        = max(1, (int) ($page ?? 1));
$totalPages  = max(1, (int) ($totalPages ?? 1));
$totalLogs   = (int) ($totalLogs ?? 0);

// Build a pagination URL that keeps the active filters
$pageUrl = function (int $p) use ($filters): string {
    $params = array_filter(
        array_merge($filters, ['page' => $p]),
        fn($v) => $v !== '' && $v !== null
    );
    return BASE_URL . '/audit?' . http_build_query($params);
};

// Human-readable labels and badge styles per action type
$actionMeta = [
    'CANCEL_TICKET'      => ['label' => 'Ticket Cancelled',    'class' => 'badge-warn',   'dot' => '#f59e0b'],
    'REFUND_ORDER'       => ['label' => 'Order Refunded',      'class' => 'badge-danger', 'dot' => '#ef4444'],
    'INVENTORY_OVERRIDE' => ['label' => 'Stock Overridden',    'class' => 'badge-info',   'dot' => '#3b82f6'],
    'EXPENSE_MODIFIED'   => ['label' => 'Expense Modified',    'class' => 'badge-indigo', 'dot' => '#6366f1'],
    'LOGIN_FAILURE'      => ['label' => 'Failed Sign-In',      'class' => 'badge-danger', 'dot' => '#dc2626'],
];

$hasFilters = ($f('action_type') !== '' || $f('user_id') !== '' || $f('start_date') !== '' || $f('end_date') !== '');
$pageStart  = max(1, $page - 2);
$pageEnd    = min($totalPages, $page + 2);
?>

<div class="sb-audit-container">

    <!-- Security Header -->
    <div class="audit-head-banner card">
        <div class="audit-head-content">
            <div class="audit-head-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <path d="M9 12l2 2 4-4"></path>
                </svg>
            </div>
            <div class="audit-head-text">
                <div class="audit-pill-row">
                    <span class="audit-security-pill">
                        <span class="sec-dot"></span> System Security &amp; Compliance (FR16)
                    </span>
                    <span class="audit-records-pill"><?= number_format($totalLogs); ?> <?= $totalLogs === 1 ? 'Event Logged' : 'Events Logged'; ?></span>
                </div>
                <h1 class="audit-main-title">Transaction &amp; Security Audit Trail</h1>
                <p class="audit-subtext">Immutable log of sensitive operations: ticket cancellations, refunds, manual stock overrides, financial modifications, and authentication attempts.</p>
            </div>
        </div>
    </div>

    <!-- Filter Form Controls -->
    <div class="audit-filter-card card">
        <div class="filter-card-header">
            <span class="filter-card-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filter Audit Logs
            </span>
            <?php if ($hasFilters): ?>
                <span class="filter-active-indicator">● Filter Active</span>
            <?php endif; ?>
        </div>

        <form class="audit-filters-form" method="GET" action="<?= BASE_URL; ?>/audit">
            <div class="filter-field">
                <label for="action_type">Operation Action</label>
                <select id="action_type" name="action_type" class="sb-select">
                    <option value="">All Action Types</option>
                    <?php foreach (($actionTypes ?? []) as $type): ?>
                        <option value="<?= htmlspecialchars($type); ?>" <?= $f('action_type') === $type ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($actionMeta[$type]['label'] ?? $type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="user_id">Staff Operator</label>
                <select id="user_id" name="user_id" class="sb-select">
                    <option value="">All Personnel</option>
                    <?php foreach (($users ?? []) as $u): ?>
                        <option value="<?= (int) $u['user_id']; ?>" <?= (string) $f('user_id') === (string) $u['user_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($u['full_name']); ?> (<?= htmlspecialchars($u['role'] ?? 'Staff'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="start_date">Date Range From</label>
                <input type="date" id="start_date" name="start_date" class="sb-input" value="<?= htmlspecialchars((string) $f('start_date')); ?>">
            </div>

            <div class="filter-field">
                <label for="end_date">Date Range To</label>
                <input type="date" id="end_date" name="end_date" class="sb-input" value="<?= htmlspecialchars((string) $f('end_date')); ?>">
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary audit-apply-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Apply Filters
                </button>
                <?php if ($hasFilters): ?>
                    <a href="<?= BASE_URL; ?>/audit" class="btn btn-ghost audit-clear-btn">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Audit Log Table Results -->
    <div class="audit-results-card card">
        <div class="audit-table-meta-bar">
            <span class="meta-count">Showing page <strong><?= $page; ?></strong> of <strong><?= $totalPages; ?></strong> (<?= number_format($totalLogs); ?> total logs)</span>
            <span class="meta-tag">Strict Monitored Ledger</span>
        </div>

        <div class="table-scroll">
            <table class="sb-audit-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Event Action</th>
                        <th>Actor / Staff</th>
                        <th>Target Model</th>
                        <th>Detailed Operation</th>
                        <th style="text-align: right;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="audit-empty-view">
                                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                <strong>No security events match your criteria.</strong>
                                <p>Try widening the date range or selecting "All Action Types".</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log):
                        $meta = $actionMeta[$log['action_type']] ?? [
                            'label' => $log['action_type'], 
                            'class' => 'badge-default', 
                            'dot'   => '#94a3b8'
                        ];
                        $ts = strtotime($log['created_at']);
                    ?>
                        <tr class="audit-row">
                            <td class="col-when">
                                <div class="audit-when-box">
                                    <span class="when-date"><?= htmlspecialchars(date('M j, Y', $ts)); ?></span>
                                    <span class="when-time"><?= htmlspecialchars(date('g:i:s A', $ts)); ?></span>
                                </div>
                            </td>

                            <td class="col-action">
                                <span class="sb-audit-badge <?= htmlspecialchars($meta['class']); ?>">
                                    <span class="badge-dot" style="background: <?= $meta['dot']; ?>;"></span>
                                    <?= htmlspecialchars($meta['label']); ?>
                                </span>
                            </td>

                            <td class="col-actor">
                                <?php if (!empty($log['actor_name'])): ?>
                                    <div class="actor-card-mini">
                                        <div class="actor-avatar-circle">
                                            <?= htmlspecialchars(mb_strtoupper(mb_substr(trim($log['actor_name']), 0, 1))); ?>
                                        </div>
                                        <div class="actor-details">
                                            <span class="actor-name"><?= htmlspecialchars($log['actor_name']); ?></span>
                                            <span class="actor-role">@<?= htmlspecialchars($log['actor_username'] ?? 'user'); ?> &bull; <?= htmlspecialchars($log['actor_role'] ?? 'Staff'); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="sb-audit-badge badge-unauth">
                                        <span class="badge-dot" style="background: #94a3b8;"></span>
                                        System / Unauthenticated
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="col-target">
                                <?php if (!empty($log['target_table'])): ?>
                                    <span class="target-chip">
                                        <code class="target-table"><?= htmlspecialchars($log['target_table']); ?></code>
                                        <?php if (!empty($log['record_id'])): ?>
                                            <span class="target-id">#<?= htmlspecialchars((string) $log['record_id']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="target-none">&mdash;</span>
                                <?php endif; ?>
                            </td>

                            <td class="col-details">
                                <div class="details-text-bubble">
                                    <?= htmlspecialchars((string) ($log['details'] ?? 'Operation executed successfully.')); ?>
                                </div>
                            </td>

                            <td class="col-ip" style="text-align: right;">
                                <code class="ip-code">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                                    <?= htmlspecialchars((string) ($log['ip_address'] ?? '127.0.0.1')); ?>
                                </code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="audit-pagination-bar">
                <nav class="sb-pager" aria-label="Audit log pages">
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars($pageUrl($page - 1)); ?>" class="pager-btn" rel="prev">&larr; Previous</a>
                    <?php else: ?>
                        <span class="pager-btn is-disabled">&larr; Previous</span>
                    <?php endif; ?>

                    <div class="pager-numbers">
                        <?php if ($pageStart > 1): ?>
                            <a href="<?= htmlspecialchars($pageUrl(1)); ?>" class="pager-num">1</a>
                            <?php if ($pageStart > 2): ?><span class="pager-ellipsis">&hellip;</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($p = $pageStart; $p <= $pageEnd; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="pager-num is-current" aria-current="page"><?= $p; ?></span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($pageUrl($p)); ?>" class="pager-num"><?= $p; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pageEnd < $totalPages): ?>
                            <?php if ($pageEnd < $totalPages - 1): ?><span class="pager-ellipsis">&hellip;</span><?php endif; ?>
                            <a href="<?= htmlspecialchars($pageUrl($totalPages)); ?>" class="pager-num"><?= $totalPages; ?></a>
                        <?php endif; ?>
                    </div>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= htmlspecialchars($pageUrl($page + 1)); ?>" class="pager-btn" rel="next">Next &rarr;</a>
                    <?php else: ?>
                        <span class="pager-btn is-disabled">Next &rarr;</span>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

</div>
