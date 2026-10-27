<?php
/**
 * ScreenBites - System Audit Logs
 * Procedural auditing module modeled after itim211-2b-shop
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Security & Audit Logs';
require_once dirname(__DIR__) . '/includes/header.php';

// Filter parameters
$actionType = trim($_GET['action_type'] ?? '');
$userId     = (int)($_GET['user_id'] ?? 0);
$startDate  = trim($_GET['start_date'] ?? '');
$endDate    = trim($_GET['end_date'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 20;
$offset     = ($page - 1) * $limit;

$where = ["1=1"];
if (!empty($actionType)) {
    $actionEsc = mysqli_real_escape_string($conn, $actionType);
    $where[] = "a.action_type = '{$actionEsc}'";
}
if ($userId > 0) {
    $where[] = "a.user_id = {$userId}";
}
if (!empty($startDate)) {
    $startEsc = mysqli_real_escape_string($conn, $startDate . ' 00:00:00');
    $where[] = "a.created_at >= '{$startEsc}'";
}
if (!empty($endDate)) {
    $endEsc = mysqli_real_escape_string($conn, $endDate . ' 23:59:59');
    $where[] = "a.created_at <= '{$endEsc}'";
}
$whereSql = implode(' AND ', $where);

// Count total matching logs
$countRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM audit_logs a WHERE {$whereSql}");
$countRow = mysqli_fetch_assoc($countRes);
$totalLogs = (int)($countRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalLogs / $limit));

// Fetch paginated logs with user details
$sql = "SELECT a.*, u.full_name, u.username, u.role
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.user_id
        WHERE {$whereSql}
        ORDER BY a.created_at DESC
        LIMIT {$limit} OFFSET {$offset}";
$res = mysqli_query($conn, $sql);

// Fetch all staff users for filter dropdown
$usersRes = mysqli_query($conn, "SELECT user_id, full_name, username, role FROM users ORDER BY full_name ASC");

$actionMeta = [
    'CANCEL_TICKET'      => ['label' => 'Ticket Cancelled',    'class' => 'badge-warn'],
    'REFUND_ORDER'       => ['label' => 'Order Refunded',      'class' => 'badge-danger'],
    'INVENTORY_OVERRIDE' => ['label' => 'Stock Overridden',    'class' => 'badge-info'],
    'EXPENSE_MODIFIED'   => ['label' => 'Expense Modified',    'class' => 'badge-indigo'],
    'LOGIN_FAILURE'      => ['label' => 'Failed Sign-In',      'class' => 'badge-danger'],
];
?>

<div class="page-head">
    <div>
        <h1>Security &amp; Compliance Audit Logs</h1>
        <p>Immutable record of critical operational actions, inventory overrides, and financial changes.</p>
    </div>
</div>

<!-- Header Banner Card -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 48px; height: 48px; border-radius: 10px; background: rgba(43, 45, 122, 0.1); display: flex; align-items: center; justify-content: center; color: var(--c-indigo);">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <path d="M9 12l2 2 4-4"></path>
            </svg>
        </div>
        <div>
            <div style="font-weight: 700; font-size: 1.05rem; color: var(--c-ink);">System Auditing &amp; Accountability</div>
            <div style="font-size: 0.85rem; color: var(--c-muted);">Captures recorded action timestamp, operator ID, target table, and IP address.</div>
        </div>
    </div>
    
    <div>
        <span style="display: inline-block; padding: 0.35rem 0.85rem; background: #eef2ff; color: var(--c-indigo); font-weight: 700; border-radius: 20px; font-size: 0.85rem; border: 1px solid #c7d2fe;">
            <?= number_format($totalLogs); ?> <?= $totalLogs === 1 ? 'Event Logged' : 'Events Logged'; ?>
        </span>
    </div>
</div>

<!-- Filters Form -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <form method="GET" action="<?= BASE_URL; ?>/audit/index.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)) auto; gap: 0.75rem; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0;">
            <label for="action_type" style="font-size: 0.82rem;">Action Type</label>
            <select id="action_type" name="action_type" class="form-control" style="padding: 0.45rem 0.65rem;">
                <option value="">All Actions</option>
                <?php foreach ($actionMeta as $typeKey => $meta): ?>
                    <option value="<?= $typeKey; ?>" <?= $actionType === $typeKey ? 'selected' : ''; ?>>
                        <?= $meta['label']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="user_id" style="font-size: 0.82rem;">Staff Member</label>
            <select id="user_id" name="user_id" class="form-control" style="padding: 0.45rem 0.65rem;">
                <option value="">All Staff</option>
                <?php while ($u = mysqli_fetch_assoc($usersRes)): ?>
                    <option value="<?= (int)$u['user_id']; ?>" <?= $userId === (int)$u['user_id'] ? 'selected' : ''; ?>>
                        <?= sanitize($u['full_name']); ?> (<?= sanitize($u['role']); ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="start_date" style="font-size: 0.82rem;">From Date</label>
            <input type="date" id="start_date" name="start_date" value="<?= sanitize($startDate); ?>" class="form-control" style="padding: 0.45rem 0.65rem;">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="end_date" style="font-size: 0.82rem;">To Date</label>
            <input type="date" id="end_date" name="end_date" value="<?= sanitize($endDate); ?>" class="form-control" style="padding: 0.45rem 0.65rem;">
        </div>

        <div style="display: flex; gap: 0.4rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">Filter</button>
            <?php if (!empty($actionType) || $userId > 0 || !empty($startDate) || !empty($endDate)): ?>
                <a href="<?= BASE_URL; ?>/audit/index.php" class="btn btn-secondary" style="padding: 0.5rem 0.75rem;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Audit Log Table -->
<div class="card">
    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th style="width: 170px;">Date &amp; Time</th>
                    <th>Staff Actor</th>
                    <th>Action</th>
                    <th>Target Record</th>
                    <th>Audit Details</th>
                    <th style="width: 110px;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res && mysqli_num_rows($res) > 0): ?>
                    <?php while ($log = mysqli_fetch_assoc($res)): 
                        $aType = $log['action_type'];
                        $meta  = $actionMeta[$aType] ?? ['label' => $aType, 'class' => 'badge-indigo'];
                    ?>
                        <tr>
                            <td style="font-size: 0.85rem; color: var(--c-muted); white-space: nowrap;">
                                <?= date('M d, Y &bull; g:i A', strtotime($log['created_at'])); ?>
                            </td>
                            <td>
                                <strong><?= sanitize($log['full_name'] ?? 'System Operator'); ?></strong>
                                <small style="display: block; color: var(--c-muted); font-size: 0.75rem;"><?= sanitize($log['role'] ?? 'Staff'); ?></small>
                            </td>
                            <td>
                                <span class="<?= $meta['class']; ?>" style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.78rem;">
                                    <?= $meta['label']; ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <code style="background: #f1f3fa; padding: 0.15rem 0.35rem; border-radius: 4px; font-size: 0.8rem;"><?= sanitize($log['target_table']); ?> #<?= (int)$log['record_id']; ?></code>
                            </td>
                            <td style="font-size: 0.88rem; color: var(--c-ink);">
                                <?= sanitize($log['details']); ?>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--c-muted); font-family: monospace;">
                                <?= sanitize($log['ip_address']); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--c-muted); padding: 3rem;">
                            No audit log records found matching the filter criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-top: 1px solid var(--c-line);">
            <span style="font-size: 0.85rem; color: var(--c-muted);">
                Showing page <?= $page; ?> of <?= $totalPages; ?>
            </span>
            <div style="display: flex; gap: 0.4rem;">
                <?php if ($page > 1): ?>
                    <a href="<?= BASE_URL; ?>/audit/index.php?page=<?= $page - 1; ?>&action_type=<?= urlencode($actionType); ?>&user_id=<?= $userId; ?>&start_date=<?= urlencode($startDate); ?>&end_date=<?= urlencode($endDate); ?>" class="btn btn-sm btn-secondary">&larr; Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= BASE_URL; ?>/audit/index.php?page=<?= $page + 1; ?>&action_type=<?= urlencode($actionType); ?>&user_id=<?= $userId; ?>&start_date=<?= urlencode($startDate); ?>&end_date=<?= urlencode($endDate); ?>" class="btn btn-sm btn-secondary">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
