<?php
/**
 * app/views/layouts/header.php
 * Opens <html>, <body>, the mobile top bar and .app-shell (closed in footer.php).
 *
 * @var string|null $pageTitle  optional, only if the controller passes it
 * @var string|null $csrfToken
 */
$fullTitle = !empty($pageTitle) ? $pageTitle . ' | ScreenBites' : 'ScreenBites';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($fullTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
    <script>
        // Available to every page script (seat-reservation.js, pos-cart.js, ...)
        window.ScreenBites = <?= json_encode(
            ['baseUrl' => BASE_URL, 'csrfToken' => $csrfToken ?? ''],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ); ?>;
    </script>
</head>
<body class="app-body">
<a class="skip-link" href="#main">Skip to content</a>

<div class="app-topbar">
    <button type="button" class="app-topbar-btn" id="navToggle"
            aria-controls="appSidebar" aria-expanded="false" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>
    <a class="app-topbar-brand" href="<?= BASE_URL; ?>/">ScreenBites</a>
</div>

<div class="app-shell">