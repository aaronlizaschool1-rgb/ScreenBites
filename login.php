<?php
/**
 * ScreenBites - Staff Login
 * Simple session authentication modeled after itim211-2b-shop
 */

require_once __DIR__ . '/includes/config.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (!empty($identifier) && !empty($password)) {
        $stmt = mysqli_prepare($conn, "SELECT user_id, username, email, password_hash, full_name, avatar_path, role, status FROM users WHERE username = ? OR email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'Active') {
                $error = 'Your account has been deactivated. Please contact an administrator.';
            } else {
                unset($user['password_hash']);
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                set_flash('success', "Welcome back, " . htmlspecialchars($user['full_name']) . "!");
                header("Location: " . BASE_URL . "/index.php");
                exit;
            }
        } else {
            $error = 'Invalid username/email or password.';
        }
    } else {
        $error = 'Please enter both username/email and password.';
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="sb-card" style="max-width: 440px; margin: 3rem auto; padding: 2rem;">
    <div style="text-align: center; margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem; color: var(--c-ink, #1d1f3a);">Sign In to ScreenBites</h1>
        <p style="color: var(--c-muted, #5d6180); font-size: 0.9rem;">Sign in with your customer or staff credentials.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="sb-alert sb-alert-danger"><?= sanitize($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL; ?>/login.php" id="loginForm" novalidate>
        <div class="form-group">
            <label for="username">Username or Email Address</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="e.g. admin_john or guest@email.com">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem; font-weight: 700;">
            Sign In &rarr;
        </button>
    </form>

    <div style="text-align: center; margin-top: 1.25rem; font-size: 0.9rem; color: var(--c-muted);">
        Don't have an account yet?<br>
        <a href="<?= BASE_URL; ?>/register.php" style="color: var(--c-indigo); font-weight: 700; text-decoration: none;">
            Create a Guest Account &rarr;
        </a>
    </div>

    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--c-line, #dcdff0); text-align: center;">
        <small style="color: var(--c-muted, #5d6180); display: block; margin-bottom: 0.6rem;">Quick Test Logins:</small>
        <div style="display: flex; gap: 0.4rem; justify-content: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-sm btn-secondary quick-user" data-u="admin_john" data-p="Password123">
                👑 Admin
            </button>
            <button type="button" class="btn btn-sm btn-secondary quick-user" data-u="ticketing_maria" data-p="Password123">
                🎟️ Ticketing
            </button>
            <button type="button" class="btn btn-sm btn-secondary quick-user" data-u="cashier_carlos" data-p="Password123">
                🍿 Cashier
            </button>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.quick-user').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('username').value = btn.getAttribute('data-u');
        document.getElementById('password').value = btn.getAttribute('data-p');
        document.getElementById('loginForm').submit();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
