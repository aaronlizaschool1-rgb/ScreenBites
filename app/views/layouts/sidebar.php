<?php
/**
 * app/views/layouts/sidebar.php
 * Role-based navigation (FR13). Also opens .app-col and <main> (closed in footer.php).
 *
 * @var string|null $flashSuccess
 * @var string|null $flashError
 */
use Core\Auth;

// Work out which link is active from the current URL
$basePath = rtrim((string) parse_url(BASE_URL, PHP_URL_PATH), '/');
$uriPath  = (string) strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$current  = '/' . trim(substr($uriPath, strlen($basePath)), '/');

$isActive = function (array $prefixes) use ($current): bool {
    foreach ($prefixes as $p) {
        if ($current === $p || str_starts_with($current, $p . '/')) {
            return true;
        }
    }
    return false;
};

// [label, path, allowed roles, URL prefixes that highlight the link, icon SVG]
$navGroups = [
    [
        'label' => null,
        'items' => [
            [
                'Dashboard', 
                '/dashboard', 
                [ROLE_ADMIN], 
                ['/dashboard'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect></svg>'
            ],
        ],
    ],
    [
        'label' => 'Tickets',
        'items' => [
            [
                'Screening Schedules', 
                '/schedules', 
                [ROLE_ADMIN, ROLE_TICKETING], 
                ['/schedules', '/ticketing'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><circle cx="12" cy="15" r="2"></circle></svg>'
            ],
            [
                'Movies', 
                '/movies', 
                [ROLE_ADMIN], 
                ['/movies'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="3"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>'
            ],
        ],
    ],
    [
        'label' => 'Snack bar',
        'items' => [
            [
                'Snackbar POS', 
                '/pos', 
                [ROLE_ADMIN, ROLE_CASHIER, ROLE_TICKETING], 
                ['/pos', '/checkout'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>'
            ],
            [
                'Inventory & Stock', 
                '/inventory', 
                [ROLE_ADMIN, ROLE_CASHIER, ROLE_TICKETING], 
                ['/inventory'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>'
            ],
            [
                'Combos & Bundles', 
                '/bundles', 
                [ROLE_ADMIN], 
                ['/bundles'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>'
            ],
        ],
    ],
    [
        'label' => 'Finance',
        'items' => [
            [
                'Utilities & Expenses', 
                '/expenses/utilities', 
                [ROLE_ADMIN], 
                ['/expenses'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>'
            ],
            [
                'Sales Report', 
                '/reports/sales', 
                [ROLE_ADMIN], 
                ['/reports/sales'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>'
            ],
            [
                'Net Income Report', 
                '/reports/financial', 
                [ROLE_ADMIN], 
                ['/reports/financial'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line><polyline points="4 8 10 3 16 8 21 3"></polyline></svg>'
            ],
            [
                'Occupancy Analytics', 
                '/reports/occupancy', 
                [ROLE_ADMIN], 
                ['/reports/occupancy'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>'
            ],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            [
                'Audit Logs', 
                '/audit', 
                [ROLE_ADMIN], 
                ['/audit'],
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>'
            ],
        ],
    ],
];
?>
<?php if (Auth::check()): $user = Auth::user(); ?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    
    <!-- Brand Header -->
    <div class="app-brand">
        <a href="<?= BASE_URL; ?>/" class="app-brand-link">
            <span class="app-brand-badge">
                <img src="<?= BASE_URL; ?>/assets/img/logo.png" alt="ScreenBites Logo" class="brand-logo-img">
            </span>
            <div class="app-brand-text">
                <span class="app-brand-title">Screen<span class="brand-accent">Bites</span></span>
                <span class="app-brand-tagline">Cinema & POS Lounge</span>
            </div>
        </a>
    </div>

    <!-- Navigation List -->
    <nav class="app-nav">
        <?php foreach ($navGroups as $group):
            $visible = array_filter($group['items'], fn($item) => Auth::hasRole($item[2]));
            if (!$visible) { continue; }
        ?>
            <div class="app-nav-group">
                <?php if ($group['label']): ?>
                    <p class="app-nav-title"><?= htmlspecialchars($group['label']); ?></p>
                <?php endif; ?>
                <ul>
                    <?php foreach ($visible as [$label, $path, , $match, $iconSvg]): 
                        $active = $isActive($match);
                    ?>
                        <li>
                            <a href="<?= BASE_URL . $path; ?>"<?= $active ? ' aria-current="page"' : ''; ?>>
                                <span class="app-nav-icon"><?= $iconSvg; ?></span>
                                <span class="app-nav-label"><?= htmlspecialchars($label); ?></span>
                                <?php if ($active): ?>
                                    <span class="app-nav-indicator"></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <!-- User Profile Card -->
    <div class="app-user">
        <div class="app-user-card">
            <div class="app-user-row">
                <div class="app-avatar-wrap">
                    <span class="app-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr(trim($user['full_name']), 0, 1))); ?></span>
                    <span class="app-status-indicator" title="Active"></span>
                </div>
                <div class="app-user-info">
                    <p class="app-user-name" title="<?= htmlspecialchars($user['full_name']); ?>"><?= htmlspecialchars($user['full_name']); ?></p>
                    <span class="app-user-role"><?= htmlspecialchars($user['role']); ?></span>
                </div>
            </div>
            <a class="app-signout-btn" href="<?= BASE_URL; ?>/logout" title="Sign out of system">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Sign Out</span>
            </a>
        </div>
    </div>
</aside>
<div class="app-scrim" id="appScrim"></div>
<?php endif; ?>

<div class="app-col">
<main class="app-main" id="main">
    <?php foreach ([['success', $flashSuccess ?? null], ['error', $flashError ?? null]] as [$type, $msg]): ?>
        <?php if (!empty($msg)): ?>
            <div class="flash flash-<?= $type; ?>" role="<?= $type === 'error' ? 'alert' : 'status'; ?>">
                <span><?= htmlspecialchars($msg); ?></span>
                <button type="button" class="flash-close" aria-label="Dismiss message">&times;</button>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
