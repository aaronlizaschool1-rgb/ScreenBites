<?php
/**
 * ScreenBites - Self-Service User Profile Management
 * Fulfills MP2: User CRUD (100% Pure Procedural PHP)
 * Profile updates, avatar replacement via includes/upload.php, and secure password changes
 */

require_once __DIR__ . '/includes/config.php';
require_login();

$userId = (int)current_user()['user_id'];

// 1. Fetch fresh user record from database
$stmt = mysqli_prepare($conn, "SELECT user_id, full_name, username, email, password_hash, avatar_path, role, status, created_at FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$userData = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$userData) {
    session_destroy();
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

$error = '';
$success = '';

// Handle POST Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action A: Update Profile Information & Avatar
    if ($action === 'update_profile') {
        $fullName     = trim($_POST['full_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $removeAvatar = !empty($_POST['remove_avatar']);

        $valErr = validate_required($fullName, 'Full Name')
            ?? validate_string_length($fullName, 2, 100, 'Full Name')
            ?? validate_required($email, 'Email Address');

        if ($valErr !== null) {
            set_flash('error', $valErr);
        } elseif (!validate_email($email)) {
            set_flash('error', 'Please enter a valid email address.');
        } else {
            // Check if email is in use by another user
            $chkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
            mysqli_stmt_bind_param($chkStmt, "si", $email, $userId);
            mysqli_stmt_execute($chkStmt);
            $dupEmail = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));
            mysqli_stmt_close($chkStmt);

            if ($dupEmail) {
                set_flash('error', 'That email address is already registered to another account.');
            } else {
                $newAvatarPath = $userData['avatar_path'];

                // Check for avatar removal request
                if ($removeAvatar && !empty($userData['avatar_path'])) {
                    $oldAbs = PROJECT_ROOT . '/' . ltrim($userData['avatar_path'], '/\\');
                    if (file_exists($oldAbs) && is_file($oldAbs)) {
                        @unlink($oldAbs);
                    }
                    $newAvatarPath = null;
                }

                // Handle file upload replacement if submitted
                if (!empty($_FILES['avatar_file']['name'])) {
                    $uploadRes = handle_image_upload($_FILES['avatar_file'], 'avatars', $userData['avatar_path']);
                    if (!$uploadRes['success']) {
                        set_flash('error', 'Avatar upload failed: ' . $uploadRes['error']);
                        header("Location: " . BASE_URL . "/profile.php");
                        exit;
                    }
                    $newAvatarPath = $uploadRes['path'];
                }

                // Update database procedurally
                $updStmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, avatar_path = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($updStmt, "sssi", $fullName, $email, $newAvatarPath, $userId);
                $ok = mysqli_stmt_execute($updStmt);
                mysqli_stmt_close($updStmt);

                if ($ok) {
                    // Update session state
                    $_SESSION['user']['full_name']   = $fullName;
                    $_SESSION['user']['email']       = $email;
                    $_SESSION['user']['avatar_path'] = $newAvatarPath;

                    audit_log('PROFILE_UPDATED', 'users', $userId, "Updated profile info for {$userData['username']}");
                    set_flash('success', 'Your profile details have been successfully updated.');
                } else {
                    set_flash('error', 'Database error updating profile: ' . mysqli_error($conn));
                }
            }
        }
        header("Location: " . BASE_URL . "/profile.php");
        exit;
    }

    // Action B: Change Password
    if ($action === 'change_password') {
        $currPassword = $_POST['current_password'] ?? '';
        $newPassword  = $_POST['new_password'] ?? '';
        $confPassword = $_POST['confirm_new_password'] ?? '';

        if (empty($currPassword) || empty($newPassword) || empty($confPassword)) {
            set_flash('error', 'All password fields are required.');
        } elseif (!password_verify($currPassword, $userData['password_hash'])) {
            set_flash('error', 'Your current password was entered incorrectly.');
        } elseif (strlen($newPassword) < 8) {
            set_flash('error', 'New password must be at least 8 characters long.');
        } elseif ($newPassword !== $confPassword) {
            set_flash('error', 'New password and confirmation do not match.');
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pwdStmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($pwdStmt, "si", $newHash, $userId);
            $pwdOk = mysqli_stmt_execute($pwdStmt);
            mysqli_stmt_close($pwdStmt);

            if ($pwdOk) {
                audit_log('PASSWORD_CHANGED', 'users', $userId, "User {$userData['username']} changed their password");
                set_flash('success', 'Your password was successfully updated.');
            } else {
                set_flash('error', 'Database error updating password: ' . mysqli_error($conn));
            }
        }
        header("Location: " . BASE_URL . "/profile.php");
        exit;
    }
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Account Settings &amp; Profile</h1>
        <p>Manage your ScreenBites identity credentials, contact email, and profile avatar.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 280px 1fr; gap: 1.5rem; align-items: start;">
    
    <!-- Left Column: User Summary Card -->
    <div class="card" style="padding: 1.5rem; text-align: center;">
        <div style="position: relative; width: 110px; height: 110px; margin: 0 auto 1rem;">
            <?php if (!empty($userData['avatar_path']) && file_exists(PROJECT_ROOT . '/' . $userData['avatar_path'])): ?>
                <img src="<?= BASE_URL . '/' . htmlspecialchars($userData['avatar_path']); ?>" 
                     alt="Avatar" 
                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 3px solid var(--c-indigo); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <?php else: ?>
                <div style="width: 100%; height: 100%; border-radius: 50%; background: linear-gradient(135deg, var(--c-indigo) 0%, #4338ca 100%); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; border: 3px solid #e0e7ff; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($userData['full_name'], 0, 1))); ?>
                </div>
            <?php endif; ?>
        </div>

        <h2 style="font-size: 1.25rem; margin: 0 0 0.25rem; color: var(--c-ink);"><?= sanitize($userData['full_name']); ?></h2>
        <p style="color: var(--c-muted); font-size: 0.88rem; margin: 0 0 0.75rem;">@<?= sanitize($userData['username']); ?></p>

        <div style="margin-bottom: 1.25rem;">
            <span style="display: inline-block; background: #eef2ff; color: #3730a3; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700;">
                <?= sanitize($userData['role']); ?>
            </span>
            <span style="display: inline-block; background: #d1fae5; color: #065f46; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; margin-left: 0.25rem;">
                <?= sanitize($userData['status']); ?>
            </span>
        </div>

        <div style="border-top: 1px dashed var(--c-line); padding-top: 0.85rem; font-size: 0.82rem; color: var(--c-muted); text-align: left;">
            <div style="margin-bottom: 0.4rem;">
                <strong>Member Since:</strong><br>
                <?= date('F j, Y', strtotime($userData['created_at'])); ?>
            </div>
            <div>
                <strong>Primary Email:</strong><br>
                <?= sanitize($userData['email'] ?? 'Not set'); ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Edit Forms -->
    <div>
        <!-- Profile Info Form -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="margin: 0 0 0.25rem; font-size: 1.15rem; color: var(--c-ink);">Profile Information</h3>
            <p style="color: var(--c-muted); font-size: 0.85rem; margin: 0 0 1.25rem;">Update your display name, contact email address, and profile photo.</p>

            <form method="POST" action="<?= BASE_URL; ?>/profile.php" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action" value="update_profile">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="full_name">Full Legal Name</label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               class="form-control" 
                               value="<?= sanitize($userData['full_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="username">Username (Permanent)</label>
                        <input type="text" 
                               id="username" 
                               value="<?= sanitize($userData['username']); ?>" 
                               class="form-control" 
                               disabled 
                               style="background: #f8fafc; color: #64748b; cursor: not-allowed;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span style="font-weight: 400; color: var(--c-muted);">(Receives digital PDF receipts)</span></label>
                    <input type="text" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           value="<?= sanitize($userData['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="avatar_file">Change Profile Photo <span style="font-weight: 400; color: var(--c-muted);">(Max 2MB &bull; JPEG, PNG, WebP)</span></label>
                    <input type="file" 
                           id="avatar_file" 
                           name="avatar_file" 
                           class="form-control" 
                           accept="image/jpeg,image/png,image/webp">
                    <small style="color: var(--c-muted); display: block; margin-top: 0.25rem;">
                        Upload a photo to replace your current avatar. Processed via native procedural upload handler.
                    </small>
                </div>

                <?php if (!empty($userData['avatar_path'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #991b1b; cursor: pointer;">
                            <input type="checkbox" name="remove_avatar" value="1">
                            Remove current profile photo and restore default initials badge
                        </label>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                    Save Profile Changes
                </button>
            </form>
        </div>

        <!-- Security & Password Change Form -->
        <div class="card" style="padding: 1.5rem;">
            <h3 style="margin: 0 0 0.25rem; font-size: 1.15rem; color: var(--c-ink);">Change Password</h3>
            <p style="color: var(--c-muted); font-size: 0.85rem; margin: 0 0 1.25rem;">Verify your current credentials and set a new secure password.</p>

            <form method="POST" action="<?= BASE_URL; ?>/profile.php" novalidate>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="form-control" 
                           placeholder="Enter current password">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-control" 
                               placeholder="Min. 8 characters">
                    </div>

                    <div class="form-group">
                        <label for="confirm_new_password">Confirm New Password</label>
                        <input type="password" 
                               id="confirm_new_password" 
                               name="confirm_new_password" 
                               class="form-control" 
                               placeholder="Re-enter new password">
                    </div>
                </div>

                <button type="submit" class="btn btn-secondary" style="font-weight: 600;">
                    Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
