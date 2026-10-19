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

// [label, path, allowed roles, URL prefixes that highlight the link]
$navGroups = [
    [
        'label' => null,
        'items' => [
            ['Dashboard', '/dashboard', [ROLE_ADMIN], ['/dashboard']],
        ],
    ],
    [
        'label' => 'Tickets',
        'items' => [
            ['Screening Schedules', '/schedules', [ROLE_ADMIN, ROLE_TICKETING], ['/schedules', '/ticketing']],
            ['Movies', '/movies', [ROLE_ADMIN], ['/movies']],
        ],
    ],
    [
        'label' => 'Snack bar',
        'items' => [
            ['Snackbar POS', '/pos', [ROLE_ADMIN, ROLE_CASHIER, ROLE_TICKETING], ['/pos', '/checkout']],
            ['Inventory & Stock', '/inventory', [ROLE_ADMIN, ROLE_CASHIER, ROLE_TICKETING], ['/inventory']],
            ['Combos & Bundles', '/bundles', [ROLE_ADMIN], ['/bundles']],
        ],
    ],
    [
        'label' => 'Finance',
        'items' => [
            ['Utilities & Expenses', '/expenses/utilities', [ROLE_ADMIN], ['/expenses']],
            ['Net Income Report', '/reports/financial', [ROLE_ADMIN], ['/reports/financial']],
            ['Occupancy Analytics', '/reports/occupancy', [ROLE_ADMIN], ['/reports/occupancy']],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            ['Audit Logs', '/audit', [ROLE_ADMIN], ['/audit']],
        ],
    ],
];
?>
<?php if (Auth::check()): $user = Auth::user(); ?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    <div class="app-brand"><a href="<?= BASE_URL; ?>/">ScreenBites</a></div>

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
                    <?php foreach ($visible as [$label, $path, , $match]): ?>
                        <li>
                            <a href="<?= BASE_URL . $path; ?>"<?= $isActive($match) ? ' aria-current="page"' : ''; ?>>
                                <?= htmlspecialchars($label); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="app-user">
        <div class="app-user-row">
            <span class="app-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr(trim($user['full_name']), 0, 1))); ?></span>
            <div class="app-user-info">
                <p class="app-user-name"><?= htmlspecialchars($user['full_name']); ?></p>
                <p class="app-user-role"><?= htmlspecialchars($user['role']); ?></p>
            </div>
        </div>
        <a class="app-signout" href="<?= BASE_URL; ?>/logout">Sign Out</a>
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