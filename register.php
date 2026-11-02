<?php
require_once __DIR__ . '/includes/config.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$error = '';
$fullName = '';
$username = '';
$email    = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $fullName        = trim($_POST['full_name'] ?? '');
    $username        = trim($_POST['username'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $valErr = validate_required($fullName, 'Full Name')
        ?? validate_string_length($fullName, 2, 100, 'Full Name')
        ?? validate_required($username, 'Username')
        ?? validate_string_length($username, 3, 50, 'Username')
        ?? validate_required($email, 'Email Address');

    if ($valErr !== null) {
        $error = $valErr;
    } elseif (!validate_email($email)) {
        $error = 'Please provide a valid email address.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $error = 'Username may only contain letters, numbers, underscores, dashes, and periods.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match. Please re-enter your password.';
    } else {
        $chkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($chkStmt, "s", $username);
        mysqli_stmt_execute($chkStmt);
        $uExists = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));
        mysqli_stmt_close($chkStmt);

        if ($uExists) {
            $error = 'That username is already taken. Please choose another username.';
        } else {
            $chkEmailStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($chkEmailStmt, "s", $email);
            mysqli_stmt_execute($chkEmailStmt);
            $eExists = mysqli_fetch_assoc(mysqli_stmt_get_result($chkEmailStmt));
            mysqli_stmt_close($chkEmailStmt);

            if ($eExists) {
                $error = 'An account with that email address already exists. Please sign in instead.';
            }
        }
    }

    $avatarRelPath = null;
    if (empty($error) && !empty($_FILES['avatar_file']['name'])) {
        $uploadRes = handle_image_upload($_FILES['avatar_file'], 'avatars');
        if (!$uploadRes['success']) {
            $error = 'Profile photo upload failed: ' . $uploadRes['error'];
        } else {
            $avatarRelPath = $uploadRes['path'];
        }
    }

    if (empty($error)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'Customer';
        $status = 'Active';

        $insSql = "INSERT INTO users (full_name, username, email, password_hash, avatar_path, role, status, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmtIns = mysqli_prepare($conn, $insSql);

        if ($stmtIns) {
            mysqli_stmt_bind_param($stmtIns, "sssssss", $fullName, $username, $email, $passwordHash, $avatarRelPath, $role, $status);
            $saved = mysqli_stmt_execute($stmtIns);
            $newUserId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmtIns);

            if ($saved && $newUserId > 0) {
                audit_log('CUSTOMER_REGISTERED', 'users', (int)$newUserId, "New customer account registered: {$username} ({$email})");

                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'user_id'     => (int)$newUserId,
                    'full_name'   => $fullName,
                    'username'    => $username,
                    'email'       => $email,
                    'avatar_path' => $avatarRelPath,
                    'role'        => $role,
                    'status'      => $status
                ];

                set_flash('success', "Welcome to ScreenBites Cinema Lounge, {$fullName}! Your account has been created.");
                header("Location: " . BASE_URL . "/index.php");
                exit;
            } else {
                $error = 'Database error registering account: ' . mysqli_error($conn);
            }
        } else {
            $error = 'Database statement error: ' . mysqli_error($conn);
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="sb-card" style="max-width: 520px; margin: 2rem auto; padding: 2rem;">
    <div style="text-align: center; margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.6rem; margin-bottom: 0.25rem; color: var(--c-ink, #1d1f3a);">Join ScreenBites</h1>
        <p style="color: var(--c-muted, #5d6180); font-size: 0.9rem;">Register your guest account for online ticketing &amp; cinema reservations.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="sb-alert sb-alert-danger"><?= sanitize($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL; ?>/register.php" enctype="multipart/form-data" id="registerForm" novalidate>
        <div class="form-group">
            <label for="full_name">Full Legal Name <span style="color: #ef4444;">*</span></label>
            <input type="text"
                   id="full_name"
                   name="full_name"
                   class="form-control"
                   value="<?= sanitize($fullName); ?>"
                   placeholder="e.g. Maria Santos">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="username">Username <span style="color: #ef4444;">*</span></label>
                <input type="text"
                       id="username"
                       name="username"
                       class="form-control"
                       value="<?= sanitize($username); ?>"
                       placeholder="e.g. maria_s">
            </div>

            <div class="form-group">
                <label for="email">Email Address <span style="color: #ef4444;">*</span></label>
                <input type="text"
                       id="email"
                       name="email"
                       class="form-control"
                       value="<?= sanitize($email); ?>"
                       placeholder="maria@example.com">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="password">Password <span style="color: #ef4444;">*</span></label>
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control"
                       placeholder="Min. 8 characters">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span style="color: #ef4444;">*</span></label>
                <input type="password"
                       id="confirm_password"
                       name="confirm_password"
                       class="form-control"
                       placeholder="Re-enter password">
            </div>
        </div>

        <div class="form-group">
            <label for="avatar_file">Profile Photo <span style="font-weight: 400; color: var(--c-muted);">(Optional &bull; Max 2MB JPEG, PNG, or WebP)</span></label>
            <input type="file"
                   id="avatar_file"
                   name="avatar_file"
                   class="form-control"
                   accept="image/jpeg,image/png,image/webp">
            <small style="color: var(--c-muted, #5d6180); display: block; margin-top: 0.25rem;">
                Uploaded avatars are strictly validated via MIME inspection and stored securely.
            </small>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.75rem; font-weight: 700; padding: 0.75rem;">
            Create My Account &rarr;
        </button>
    </form>

    <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--c-line, #dcdff0); font-size: 0.9rem; color: var(--c-muted, #5d6180);">
        Already have an account?
        <a href="<?= BASE_URL; ?>/login.php" style="color: var(--c-indigo, #2b2d7a); font-weight: 700; text-decoration: none;">
            Sign In here
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
