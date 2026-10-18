<?php
/**
 * app/views/auth/login.php  (FR13)
 * Standalone view ($layout = null): renders its own full HTML page.
 *
 * @var string|null $error
 * @var string      $csrfToken
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | ScreenBites</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
</head>
<body class="login-page">
    <main class="ticket">

        <header class="ticket-top">
            <div class="ticket-marquee" aria-hidden="true"></div>
            <h1>ScreenBites</h1>
            <p>Sign in to manage tickets, snacks, and reports.</p>
        </header>

        <div class="ticket-perf" aria-hidden="true"><span></span></div>

        <form class="ticket-body" method="POST" action="<?= BASE_URL; ?>/login" autocomplete="on" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">

            <?php if (!empty($error)): ?>
                <div class="login-error" role="alert"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="login-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       autocomplete="username" autocapitalize="none" spellcheck="false">
            </div>

            <div class="login-field">
                <label for="password">Password</label>
                <div class="login-pw">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <button type="button" class="login-pw-toggle" id="pwToggle" aria-pressed="false" aria-controls="password">Show</button>
                </div>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">Sign in</button>
        </form>

    </main>

    <script>
    (function () {
        var pw = document.getElementById('password');
        var toggle = document.getElementById('pwToggle');
        var form = document.getElementById('loginForm');
        var btn = document.getElementById('loginBtn');

        toggle.addEventListener('click', function () {
            var show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            toggle.textContent = show ? 'Hide' : 'Show';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        });

        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.textContent = 'Signing in...';
        });
    })();
    </script>
</body>
</html>