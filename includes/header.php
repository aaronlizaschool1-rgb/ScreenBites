<?php
require_once __DIR__ . '/config.php';

$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['qty'] ?? 1);
    }
}
$user = current_user();

$fullTitle = !empty($pageTitle) ? sanitize($pageTitle) . ' | ScreenBites' : 'ScreenBites Cinema & POS Lounge';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

$isActive = function(string $path) use ($currentUri): bool {
    if ($path === '/index.php' || $path === '/') {
        return (str_ends_with($currentUri, 'ScreenBites/') || str_ends_with($currentUri, 'ScreenBites/index.php'));
    }
    return str_contains($currentUri, $path);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $fullTitle; ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL; ?>/assets/img/logo.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL; ?>/assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/seat-matrix.css">
</head>
<body class="app-body">
<a class="skip-link" href="#main">Skip to content</a>

<!-- Mobile Topbar with Nav Toggle -->
<div class="app-topbar">
    <button type="button" class="app-topbar-btn" id="navToggle"
            aria-controls="appSidebar" aria-expanded="false" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>
    <a class="app-topbar-brand" href="<?= BASE_URL; ?>/index.php">
        <img src="<?= BASE_URL; ?>/assets/img/logo.png" alt="ScreenBites" class="topbar-logo-img">
        <span>ScreenBites</span>
    </a>
</div>

<div class="app-shell">

<!-- Left Sidebar Navigation -->
<aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    <div class="app-brand">
        <a href="<?= BASE_URL; ?>/index.php" class="app-brand-link">
            <span class="app-brand-badge">
                <img src="<?= BASE_URL; ?>/assets/img/logo.png" alt="ScreenBites Logo" class="brand-logo-img">
            </span>
            <div class="app-brand-text">
                <span class="app-brand-title">Screen<span class="brand-accent">Bites</span></span>
                <span class="app-brand-tagline">Cinema &amp; POS Lounge</span>
            </div>
        </a>
    </div>

    <nav class="app-nav">
        <!-- Overview Group -->
        <div class="app-nav-group">
            <p class="app-nav-title">Overview</p>
            <ul>
                <?php if (is_admin()): ?>
                    <li>
                        <a href="<?= BASE_URL; ?>/dashboard.php"<?= $isActive('dashboard.php') ? ' aria-current="page"' : ''; ?>>
                            <span class="app-nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect></svg>
                            </span>
                            <span class="app-nav-label">Dashboard</span>
                            <?php if ($isActive('dashboard.php')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="<?= BASE_URL; ?>/index.php"<?= $isActive('/index.php') ? ' aria-current="page"' : ''; ?>>
                        <span class="app-nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        </span>
                        <span class="app-nav-label">Now Showing</span>
                        <?php if ($isActive('/index.php')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Box Office Group -->
        <div class="app-nav-group">
            <p class="app-nav-title">Box Office</p>
            <ul>
                <?php if (is_logged_in()): ?>
                    <li>
                        <a href="<?= BASE_URL; ?>/movies/index.php"<?= $isActive('movies') ? ' aria-current="page"' : ''; ?>>
                            <span class="app-nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="3"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                            </span>
                            <span class="app-nav-label">Movies</span>
                            <?php if ($isActive('movies')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="<?= BASE_URL; ?>/schedules/index.php"<?= $isActive('schedules') ? ' aria-current="page"' : ''; ?>>
                        <span class="app-nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><circle cx="12" cy="15" r="2"></circle></svg>
                        </span>
                        <span class="app-nav-label">Schedules</span>
                        <?php if ($isActive('schedules')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Concessions Group -->
        <div class="app-nav-group">
            <p class="app-nav-title">Concessions</p>
            <ul>
                <li>
                    <a href="<?= BASE_URL; ?>/concessions/index.php"<?= ($isActive('concessions/index.php') || $isActive('concessions/?')) ? ' aria-current="page"' : ''; ?>>
                        <span class="app-nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                        </span>
                        <span class="app-nav-label">Snacks &amp; Concessions</span>
                        <?php if ($isActive('concessions/index.php')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                    </a>
                </li>
                <?php if (is_admin()): ?>
                    <li>
                        <a href="<?= BASE_URL; ?>/concessions/combos.php"<?= $isActive('combos') ? ' aria-current="page"' : ''; ?>>
                            <span class="app-nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
                            </span>
                            <span class="app-nav-label">Combos &amp; Bundles</span>
                            <?php if ($isActive('combos')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="<?= BASE_URL; ?>/view_cart.php"<?= $isActive('view_cart.php') ? ' aria-current="page"' : ''; ?>>
                        <span class="app-nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                        </span>
                        <span class="app-nav-label">Cart</span>
                        <?php if ($cartCount > 0): ?>
                            <span class="sb-cart-badge"><?= $cartCount; ?></span>
                        <?php endif; ?>
                        <?php if ($isActive('view_cart.php')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Finance Group (Admin) -->
        <?php if (is_admin()): ?>
            <div class="app-nav-group">
                <p class="app-nav-title">Finance</p>
                <ul>
                    <li>
                        <a href="<?= BASE_URL; ?>/utilities/index.php"<?= $isActive('utilities') ? ' aria-current="page"' : ''; ?>>
                            <span class="app-nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
                            </span>
                            <span class="app-nav-label">Utilities &amp; Expenses</span>
                            <?php if ($isActive('utilities')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL; ?>/reports/sales.php"<?= $isActive('reports/sales.php') ? ' aria-current="page"' : ''; ?>>
                            <span class="app-nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            </span>
                            <span class="app-nav-label">Sales Report</span>
                            <?php if ($isActive('reports/sales.php')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL; ?>/reports/financial.php"<?= $isActive('reports/financial.php') ? ' aria-current="page"' : ''; ?>>
                            <span class="app-nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            </span>
                            <span class="app-nav-label">Net Income (P&amp;L)</span>
                            <?php if ($isActive('reports/financial.php')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL; ?>/reports/occupancy.php"<?= $isActive('reports/occupancy.php') ? ' aria-current="page"' : ''; ?>>
                            <span class="app-nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                            </span>
                            <span class="app-nav-label">Occupancy Analytics</span>
                            <?php if ($isActive('reports/occupancy.php')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- System Group (Admin) -->
            <div class="app-nav-group">
                <p class="app-nav-title">System</p>
                <ul>
                    <li>
                        <a href="<?= BASE_URL; ?>/audit/index.php"<?= $isActive('audit') ? ' aria-current="page"' : ''; ?>>
                            <span class="app-nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>
                            </span>
                            <span class="app-nav-label">Audit Logs</span>
                            <?php if ($isActive('audit')): ?><span class="app-nav-indicator"></span><?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </nav>

    <!-- Sidebar User / Auth Card -->
    <div class="app-user">
        <?php if (is_logged_in()): ?>
            <div class="app-user-card">
                <div class="app-user-row">
                    <div class="app-avatar-wrap">
                        <span class="app-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr(trim($user['full_name'] ?? $user['username']), 0, 1))); ?></span>
                        <span class="app-status-indicator" title="Active"></span>
                    </div>
                    <div class="app-user-info">
                        <p class="app-user-name" title="<?= sanitize($user['full_name'] ?? $user['username']); ?>"><?= sanitize($user['full_name'] ?? $user['username']); ?></p>
                        <span class="app-user-role"><?= sanitize($user['role'] ?? 'Staff'); ?></span>
                    </div>
                </div>
                <a class="app-signout-btn" href="<?= BASE_URL; ?>/logout.php" title="Sign out">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Sign Out</span>
                </a>
            </div>
        <?php else: ?>
            <div style="padding: 0.5rem;">
                <a href="<?= BASE_URL; ?>/login.php" class="btn btn-secondary" style="width: 100%; text-align: center; display: block; font-weight: 600;">
                    Staff Console Sign In
                </a>
            </div>
        <?php endif; ?>
    </div>
</aside>

<!-- Scrim for mobile sidebar -->
<div class="app-scrim" id="appScrim"></div>

<!-- Main Application Column -->
<div class="app-col">
<main class="app-main" id="main">
<div class="sb-container">
<?php include __DIR__ . '/alert.php'; ?>
