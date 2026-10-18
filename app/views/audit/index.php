   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   
<?php
/**
 * app/views/audit/index.php  (FR16)
 * Injected: $logs, $users, $actionTypes, $filters, $page, $totalPages, $totalLogs
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
    'CANCEL_TICKET'      => ['label' => 'Ticket cancelled',    'class' => 'is-warn'],
    'REFUND_ORDER'       => ['label' => 'Order refunded',      'class' => 'is-danger'],
    'INVENTORY_OVERRIDE' => ['label' => 'Inventory override',  'class' => 'is-info'],
    'EXPENSE_MODIFIED'   => ['label' => 'Expense modified',    'class' => 'is-info'],
    'LOGIN_FAILURE'      => ['label' => 'Login failure',       'class' => 'is-danger'],
];

$hasFilters = ($f('action_type') !== '' || $f('user_id') !== '' || $f('start_date') !== '' || $f('end_date') !== '');
$pageStart  = max(1, $page - 2);
$pageEnd    = min($totalPages, $page + 2);
?>

<div class="audit-page">
    <h1>Audit logs</h1>
    <p class="audit-sub">A record of sensitive actions: cancellations, refunds, stock overrides, expense edits, and failed sign-ins.</p>

    <!-- Filters (GET so results are bookmarkable) -->
    <form class="audit-filters" method="GET" action="<?= BASE_URL; ?>/audit">
        <div class="field">
            <label for="action_type">Action</label>
            <select id="action_type" name="action_type">
                <option value="">All actions</option>
                <?php foreach (($actionTypes ?? []) as $type): ?>
                    <option value="<?= htmlspecialchars($type); ?>" <?= $f('action_type') === $type ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($actionMeta[$type]['label'] ?? $type); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="user_id">User</label>
            <select id="user_id" name="user_id">
                <option value="">All users</option>
                <?php foreach (($users ?? []) as $u): ?>
                    <option value="<?= (int) $u['user_id']; ?>" <?= (string) $f('user_id') === (string) $u['user_id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($u['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="start_date">From</label>
            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars((string) $f('start_date')); ?>">
        </div>

        <div class="field">
            <label for="end_date">To</label>
            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars((string) $f('end_date')); ?>">
        </div>

        <div class="actions">
            <button type="submit" class="audit-btn">Apply filters</button>
            <?php if ($hasFilters): ?>
                <a href="<?= BASE_URL; ?>/audit" class="audit-btn is-ghost">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="audit-summary">
        <span><?= number_format($totalLogs); ?> <?= $totalLogs === 1 ? 'record' : 'records'; ?> found</span>
        <span>Page <?= $page; ?> of <?= $totalPages; ?></span>
    </div>

    <div class="audit-table-wrap">
        <table class="audit-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Action</th>
                    <th>Performed by</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>IP address</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6">
                        <div class="audit-empty">
                            <strong>No audit records match these filters.</strong>
                            Try a wider date range or clear the filters.
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log):
                    $meta = $actionMeta[$log['action_type']] ?? ['label' => $log['action_type'], 'class' => ''];
                    $ts   = strtotime($log['created_at']);
                ?>
                <tr>
                    <td class="when">
                        <?= htmlspecialchars(date('M j, Y', $ts)); ?>
                        <small><?= htmlspecialchars(date('g:i:s A', $ts)); ?></small>
                    </td>
                    <td>
                        <span class="audit-badge <?= htmlspecialchars($meta['class']); ?>">
                            <?= htmlspecialchars($meta['label']); ?>
                        </span>
                    </td>
                    <td class="actor">
                        <?php if (!empty($log['actor_name'])): ?>
                            <?= htmlspecialchars($log['actor_name']); ?>
                            <small>
                                @<?= htmlspecialchars($log['actor_username'] ?? ''); ?>
                                <?php if (!empty($log['actor_role'])): ?>
                                    (<?= htmlspecialchars($log['actor_role']); ?>)
                                <?php endif; ?>
                            </small>
                        <?php else: ?>
                            <span class="audit-badge">Unknown / not signed in</span>
                        <?php endif; ?>
                    </td>
                    <td class="target">
                        <?php if (!empty($log['target_table'])): ?>
                            <code><?= htmlspecialchars($log['target_table']); ?></code>
                            <?php if (!empty($log['record_id'])): ?>
                                #<?= htmlspecialchars((string) $log['record_id']); ?>
                            <?php endif; ?>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                    <td class="details"><?= htmlspecialchars((string) ($log['details'] ?? '')); ?></td>
                    <td class="ip"><?= htmlspecialchars((string) ($log['ip_address'] ?? '')); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="audit-pager" aria-label="Audit log pages">
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars($pageUrl($page - 1)); ?>" rel="prev">Previous</a>
        <?php else: ?>
            <span class="is-disabled">Previous</span>
        <?php endif; ?>

        <?php if ($pageStart > 1): ?>
            <a href="<?= htmlspecialchars($pageUrl(1)); ?>">1</a>
            <?php if ($pageStart > 2): ?><span class="is-disabled">&hellip;</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $pageStart; $p <= $pageEnd; $p++): ?>
            <?php if ($p === $page): ?>
                <span class="is-current" aria-current="page"><?= $p; ?></span>
            <?php else: ?>
                <a href="<?= htmlspecialchars($pageUrl($p)); ?>"><?= $p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($pageEnd < $totalPages): ?>
            <?php if ($pageEnd < $totalPages - 1): ?><span class="is-disabled">&hellip;</span><?php endif; ?>
            <a href="<?= htmlspecialchars($pageUrl($totalPages)); ?>"><?= $totalPages; ?></a>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars($pageUrl($page + 1)); ?>" rel="next">Next</a>
        <?php else: ?>
            <span class="is-disabled">Next</span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</div>