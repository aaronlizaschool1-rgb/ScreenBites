<?php

$posterDir = ROOT_PATH . '/public/uploads/posters';
$posterFiles = glob($posterDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
$posters = [];
foreach ($posterFiles as $file) {
    $bn = basename($file);
    if ($bn !== '.gitkeep') {
        $posters[] = $bn;
    }
}
if (!empty($posters)) {

    while (count($posters) < 48) {
        $posters = array_merge($posters, $posters);
    }
    $posters = array_slice($posters, 0, 48);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | ScreenBites Cinema Lounge</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL; ?>/assets/img/logo.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL; ?>/assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
</head>
<body class="login-page">

    <div class="netflix-poster-backdrop" aria-hidden="true">
        <div class="netflix-poster-grid">
            <?php foreach ($posters as $idx => $p): ?>
                <div class="netflix-poster-card">
                    <img src="<?= BASE_URL; ?>/uploads/posters/<?= htmlspecialchars($p); ?>" alt="" loading="lazy">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="netflix-overlay-radial"></div>
        <div class="netflix-overlay-linear"></div>
        <div class="netflix-spotlight-beam"></div>
    </div>

    <main class="ticket-container">

        <article class="ticket" aria-label="Cinema Admission Login Ticket">

            <header class="ticket-top">

                <div class="ticket-marquee" aria-hidden="true">
                    <span class="marquee-track"></span>
                </div>

                <div class="ticket-header-content">
                    <div class="ticket-brand-row">
                        <div class="ticket-brand-badge">
                            <img src="<?= BASE_URL; ?>/assets/img/logo.png" alt="ScreenBites Logo" class="ticket-logo-img">
                        </div>
                        <div class="ticket-stub-tag">ADMIT ONE &bull; VIP CINEMA</div>
                    </div>

                    <h1 class="ticket-brand-title">Screen<span class="ticket-gold-text">Bites</span></h1>
                    <p class="ticket-brand-sub">Cinema Box Office &amp; Concessions Management</p>
                </div>

                <div class="ticket-metadata-strip">
                    <div class="meta-item"><span>GATE</span><strong>01</strong></div>
                    <div class="meta-item"><span>HALL</span><strong>VIP-A</strong></div>
                    <div class="meta-item"><span>ROW</span><strong>AA</strong></div>
                    <div class="meta-item"><span>SEAT</span><strong>07</strong></div>
                </div>
            </header>

            <div class="ticket-perf" aria-hidden="true">
                <div class="notch-left"></div>
                <div class="perf-line"></div>
                <div class="notch-right"></div>
            </div>

            <div class="ticket-body">
                <form method="POST" action="<?= BASE_URL; ?>/login" autocomplete="on" id="loginForm" class="ticket-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">

                    <?php if (!empty($error)): ?>
                        <div class="login-error" role="alert">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <span><?= htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="login-field">
                        <label for="username">Username or ID</label>
                        <div class="input-icon-wrap">
                            <span class="field-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            </span>
                            <input type="text" id="username" name="username" required autofocus
                                   autocomplete="username" autocapitalize="none" spellcheck="false"
                                   placeholder="Enter staff username">
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="password">Password</label>
                        <div class="input-icon-wrap login-pw">
                            <span class="field-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            </span>
                            <input type="password" id="password" name="password" required
                                   autocomplete="current-password" placeholder="••••••••••••">
                            <button type="button" class="login-pw-toggle" id="pwToggle" aria-pressed="false" aria-controls="password" title="Toggle password visibility">
                                <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        <span>Sign In to Console</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </form>

                <div class="fast-login-tray">
                    <span class="tray-label">Quick Sign-in:</span>
                    <div class="chip-group">
                        <button type="button" class="fast-chip" data-user="admin_john" data-pass="Password123" title="Log in as Administrator">
                            <span class="chip-role">👑</span> Admin
                        </button>
                        <button type="button" class="fast-chip" data-user="ticketing_maria" data-pass="Password123" title="Log in as Ticketing Staff">
                            <span class="chip-role">🎟️</span> Ticketing
                        </button>
                        <button type="button" class="fast-chip" data-user="cashier_carlos" data-pass="Password123" title="Log in as Snackbar Cashier">
                            <span class="chip-role">🍿</span> Cashier
                        </button>
                    </div>
                </div>

                <div class="ticket-barcode-footer" aria-hidden="true">
                    <div class="barcode-bars">
                        <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                    </div>
                    <div class="barcode-num">849204 &bull; SCREENBITES &bull; 2026</div>
                </div>
            </div>

        </article>
    </main>

    <script>
    (function () {
        var pw = document.getElementById('password');
        var userField = document.getElementById('username');
        var toggle = document.getElementById('pwToggle');
        var eyeIcon = document.getElementById('eyeIcon');
        var form = document.getElementById('loginForm');
        var btn = document.getElementById('loginBtn');
        var chips = document.querySelectorAll('.fast-chip');

        // Toggle password show/hide with dynamic SVG
        toggle.addEventListener('click', function () {
            var show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            if (show) {
                eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        });

        // Fast Login Chip Fill
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                var u = chip.getAttribute('data-user');
                var p = chip.getAttribute('data-pass');
                userField.value = u;
                pw.value = p;
                userField.focus();

                // Visual ripple feedback
                chip.classList.add('chip-active');
                setTimeout(function () { chip.classList.remove('chip-active'); }, 300);
            });
        });

        // Button submit loading animation
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<svg class="spinner-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path></svg> <span>Authorizing...</span>';
        });
    })();
    </script>
</body>
</html>
