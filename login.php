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
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $uEsc = mysqli_real_escape_string($conn, $username);
        $res = mysqli_query($conn, "SELECT user_id, username, password_hash, full_name, role, status FROM users WHERE username = '{$uEsc}' LIMIT 1");
        $user = mysqli_fetch_assoc($res);

        if ($user && $user['status'] === 'Active' && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            header("Location: " . BASE_URL . "/index.php");
            exit;
        } else {
            $error = 'Invalid staff username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}

$pageTitle = 'Staff Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="sb-card" style="max-width: 440px; margin: 3rem auto; padding: 2rem;">
    <div style="text-align: center; margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem; color: var(--c-ink, #1d1f3a);">Staff Console Sign In</h1>
        <p style="color: var(--c-muted, #5d6180); font-size: 0.9rem;">Sign in to access movies and schedules management.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="sb-alert sb-alert-danger"><?= sanitize($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL; ?>/login.php" id="loginForm">
        <div class="form-group">
            <label for="username">Staff Username</label>
            <input type="text" id="username" name="username" class="form-control" required autofocus placeholder="e.g. admin_john">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
            Sign In &rarr;
        </button>
    </form>

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
