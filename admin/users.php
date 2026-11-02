<?php
/**
 * ScreenBites - Administrative User Management Console
 * Fulfills MP2: User CRUD (100% Pure Procedural PHP)
 * User auditing, inline role update, and active/inactive status toggling with self-demotion guards
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$currentAdminId = (int)current_user()['user_id'];

// Handle POST Mutations (Action-Redirect Flow)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action'] ?? '';
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);

    if ($targetUserId <= 0) {
        set_flash('error', 'Invalid user target specified.');
        header("Location: " . BASE_URL . "/admin/users.php");
        exit;
    }

    // Fetch target user metadata
    $chkStmt = mysqli_prepare($conn, "SELECT user_id, username, full_name, role, status FROM users WHERE user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($chkStmt, "i", $targetUserId);
    mysqli_stmt_execute($chkStmt);
    $targetUser = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));
    mysqli_stmt_close($chkStmt);

    if (!$targetUser) {
        set_flash('error', 'The requested user account does not exist.');
        header("Location: " . BASE_URL . "/admin/users.php");
        exit;
    }

    // 1. Toggle Active / Inactive Status
    if ($action === 'toggle_status') {
        if ($targetUserId === $currentAdminId) {
            set_flash('error', 'Security Violation: You cannot deactivate your own active administrator account.');
            header("Location: " . BASE_URL . "/admin/users.php");
            exit;
        }

        $newStatus = ($targetUser['status'] === 'Active') ? 'Inactive' : 'Active';
        $updStmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($updStmt, "si", $newStatus, $targetUserId);
        $ok = mysqli_stmt_execute($updStmt);
        mysqli_stmt_close($updStmt);

        if ($ok) {
            audit_log('USER_STATUS_TOGGLED', 'users', $targetUserId, "Changed status of {$targetUser['username']} to {$newStatus}");
            set_flash('success', "Account status for {$targetUser['full_name']} (@{$targetUser['username']}) set to {$newStatus}.");
        } else {
            set_flash('error', 'Database error updating user status: ' . mysqli_error($conn));
        }
        header("Location: " . BASE_URL . "/admin/users.php");
        exit;
    }

    // 2. Update Role Assignment
    if ($action === 'update_role') {
        $newRole = trim($_POST['new_role'] ?? '');
        $allowedRoles = ['Customer', 'Administrator', 'Ticketing Staff', 'Snackbar Cashier'];

        if (!in_array($newRole, $allowedRoles, true)) {
            set_flash('error', 'Invalid role assignment specified.');
            header("Location: " . BASE_URL . "/admin/users.php");
            exit;
        }

        // Self-demotion guard
        if ($targetUserId === $currentAdminId && $newRole !== 'Administrator') {
            set_flash('error', 'Security Violation: You cannot revoke or demote your own Administrator privileges.');
            header("Location: " . BASE_URL . "/admin/users.php");
            exit;
        }

        $updRoleStmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($updRoleStmt, "si", $newRole, $targetUserId);
        $roleOk = mysqli_stmt_execute($updRoleStmt);
        mysqli_stmt_close($updRoleStmt);

        if ($roleOk) {
            audit_log('USER_ROLE_UPDATED', 'users', $targetUserId, "Promoted/Changed role of {$targetUser['username']} from {$targetUser['role']} to {$newRole}");
            set_flash('success', "Role for {$targetUser['full_name']} (@{$targetUser['username']}) updated to {$newRole}.");
        } else {
            set_flash('error', 'Database error updating user role: ' . mysqli_error($conn));
        }
        header("Location: " . BASE_URL . "/admin/users.php");
        exit;
    }
}

// Filter parameters
$search       = trim($_GET['search'] ?? '');
$roleFilter   = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if (!empty($roleFilter)) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
    $types .= "s";
}

if (!empty($statusFilter)) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$whereClause = implode(" AND ", $where);

// Compute Overall KPIs
$kpiRes = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total_users,
        SUM(CASE WHEN role = 'Customer' THEN 1 ELSE 0 END) AS total_customers,
        SUM(CASE WHEN role IN ('Administrator', 'Ticketing Staff', 'Snackbar Cashier') THEN 1 ELSE 0 END) AS total_staff,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_users
    FROM users
");
$kpiData = mysqli_fetch_assoc($kpiRes);
$totalUsers = (int)($kpiData['total_users'] ?? 0);
$totalCust  = (int)($kpiData['total_customers'] ?? 0);
$totalStaff = (int)($kpiData['total_staff'] ?? 0);
$activeUsers = (int)($kpiData['active_users'] ?? 0);

// Fetch User Records
$query = "SELECT user_id, full_name, username, email, avatar_path, role, status, created_at 
          FROM users 
          WHERE {$whereClause} 
          ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($types)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$usersList = [];
while ($row = mysqli_fetch_assoc($res)) {
    $usersList[] = $row;
}
mysqli_stmt_close($stmt);

$pageTitle = 'User Management';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>User Account Management</h1>
        <p>Audit registered cinema guests, manage staff privileges, and control account activation status.</p>
    </div>
</div>

<!-- Operational KPI Cards -->
<div class="kpi-grid">
    <div class="kpi is-key">
        <p class="kpi-label">Registered Accounts</p>
        <p class="kpi-value" style="color: var(--c-indigo);"><?= number_format($totalUsers); ?></p>
        <p class="kpi-note"><?= number_format($activeUsers); ?> active users</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Cinema Guests</p>
        <p class="kpi-value" style="color: var(--accent-gold, #f59e0b);"><?= number_format($totalCust); ?></p>
        <p class="kpi-note">Customer self-registrations</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Internal Cinema Staff</p>
        <p class="kpi-value" style="color: #0284c7;"><?= number_format($totalStaff); ?></p>
        <p class="kpi-note">Admins, ticketing, cashiers</p>
    </div>

    <div class="kpi">
        <p class="kpi-label">Account Health</p>
        <p class="kpi-value" style="color: #059669;">
            <?= $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 100; ?>%
        </p>
        <p class="kpi-note">Active standing ratio</p>
    </div>
</div>

<!-- Filter Toolbar (Native Form GET) -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="<?= BASE_URL; ?>/admin/users.php" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 220px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--c-muted); display: block; margin-bottom: 0.25rem;">Search Name, Username, or Email</label>
            <input type="text" name="search" value="<?= sanitize($search); ?>" placeholder="e.g. Maria, admin_john, maria@email..." class="form-control">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--c-muted); display: block; margin-bottom: 0.25rem;">Filter Role</label>
            <select name="role" class="form-control">
                <option value="">All Roles</option>
                <option value="Customer" <?= $roleFilter === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                <option value="Administrator" <?= $roleFilter === 'Administrator' ? 'selected' : ''; ?>>Administrator</option>
                <option value="Ticketing Staff" <?= $roleFilter === 'Ticketing Staff' ? 'selected' : ''; ?>>Ticketing Staff</option>
                <option value="Snackbar Cashier" <?= $roleFilter === 'Snackbar Cashier' ? 'selected' : ''; ?>>Snackbar Cashier</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--c-muted); display: block; margin-bottom: 0.25rem;">Filter Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">Filter</button>
            <a href="<?= BASE_URL; ?>/admin/users.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Reset</a>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="card">
    <div style="padding: 1.25rem 1.25rem 0.5rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin: 0; font-size: 1.15rem; color: var(--c-ink);">User Accounts (<?= count($usersList); ?>)</h3>
            <p style="margin: 0.2rem 0 0; color: var(--c-muted); font-size: 0.85rem;">Manage security privileges and access statuses.</p>
        </div>
    </div>

    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th style="width: 50px;"></th>
                    <th>Full Name &amp; Username</th>
                    <th>Email Address</th>
                    <th style="width: 180px;">Role Assignment</th>
                    <th style="text-align: center; width: 100px;">Status</th>
                    <th>Member Since</th>
                    <th style="text-align: center; width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($usersList)): ?>
                    <?php foreach ($usersList as $u): 
                        $isSelf = ((int)$u['user_id'] === $currentAdminId);
                    ?>
                        <tr>
                            <!-- Avatar Thumbnail -->
                            <td>
                                <?php if (!empty($u['avatar_path']) && file_exists(PROJECT_ROOT . '/' . $u['avatar_path'])): ?>
                                    <img src="<?= BASE_URL . '/' . htmlspecialchars($u['avatar_path']); ?>" 
                                         alt="Avatar" 
                                         style="width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 1.5px solid var(--c-line);">
                                <?php else: ?>
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: #e0e7ff; color: #3730a3; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 700;">
                                        <?= htmlspecialchars(mb_strtoupper(mb_substr($u['full_name'], 0, 1))); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Name & Username -->
                            <td>
                                <strong style="font-size: 0.92rem; color: var(--c-ink); display: block;">
                                    <?= sanitize($u['full_name']); ?>
                                    <?php if ($isSelf): ?>
                                        <span style="font-size: 0.72rem; background: #eef2ff; color: #3730a3; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 700; margin-left: 0.25rem;">YOU</span>
                                    <?php endif; ?>
                                </strong>
                                <small style="color: var(--c-muted); font-size: 0.8rem;">@<?= sanitize($u['username']); ?></small>
                            </td>

                            <!-- Email -->
                            <td style="font-size: 0.88rem;">
                                <?= sanitize($u['email'] ?? 'Not set'); ?>
                            </td>

                            <!-- Role Update Form (Zero-JSON auto-submit) -->
                            <td>
                                <form method="POST" action="<?= BASE_URL; ?>/admin/users.php" style="margin: 0;">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="target_user_id" value="<?= (int)$u['user_id']; ?>">
                                    <select name="new_role" 
                                            class="form-control" 
                                            style="padding: 0.3rem 0.5rem; font-size: 0.82rem; font-weight: 600;" 
                                            onchange="this.form.submit()"
                                            <?= $isSelf ? 'disabled title="You cannot demote your own administrator privileges"' : ''; ?>>
                                        <option value="Customer" <?= $u['role'] === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                                        <option value="Administrator" <?= $u['role'] === 'Administrator' ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="Ticketing Staff" <?= $u['role'] === 'Ticketing Staff' ? 'selected' : ''; ?>>Ticketing Staff</option>
                                        <option value="Snackbar Cashier" <?= $u['role'] === 'Snackbar Cashier' ? 'selected' : ''; ?>>Snackbar Cashier</option>
                                    </select>
                                </form>
                            </td>

                            <!-- Status Badge -->
                            <td style="text-align: center;">
                                <?php if ($u['status'] === 'Active'): ?>
                                    <span style="color: #065f46; background: #d1fae5; font-size: 0.78rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 999px;">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span style="color: #991b1b; background: #fee2e2; font-size: 0.78rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 999px;">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Created Date -->
                            <td style="font-size: 0.85rem; color: var(--c-muted);">
                                <?= date('M d, Y', strtotime($u['created_at'])); ?>
                            </td>

                            <!-- Status Toggle Action Form -->
                            <td style="text-align: center;">
                                <?php if ($isSelf): ?>
                                    <span style="font-size: 0.78rem; color: var(--c-muted); font-style: italic;">Protected</span>
                                <?php else: ?>
                                    <form method="POST" action="<?= BASE_URL; ?>/admin/users.php" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="target_user_id" value="<?= (int)$u['user_id']; ?>">
                                        <?php if ($u['status'] === 'Active'): ?>
                                            <button type="submit" 
                                                    class="btn btn-sm btn-secondary" 
                                                    style="color: #991b1b; border-color: #fecaca; background: #fff5f5; font-size: 0.78rem;" 
                                                    onclick="return confirm('Are you sure you want to deactivate account @<?= sanitize($u['username']); ?>?')">
                                                Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" 
                                                    class="btn btn-sm btn-secondary" 
                                                    style="color: #065f46; border-color: #a7f3d0; background: #f0fdf4; font-size: 0.78rem;">
                                                Activate
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--c-muted);">
                            No user records matched the selected filters.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
