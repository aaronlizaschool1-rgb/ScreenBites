<?php
/**
 * app/views/auth/login.php  (FR13)
 * Standalone view ($layout = null): renders its own full HTML page.
 * Injected: $error (string|null), $csrfToken (string)
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | ScreenBites</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
</head>
<body class="login-page">
    <main class="login-card">
        <h1>ScreenBites</h1>
        <p class="login-sub">Sign in to manage tickets, snacks, and reports.</p>

        <?php if (!empty($error)): ?>
            <div class="login-error" role="alert"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL; ?>/login" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">

            <div class="login-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>

            <div class="login-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="login-btn">Sign in</button>
        </form>
    </main>
</body>
</html>