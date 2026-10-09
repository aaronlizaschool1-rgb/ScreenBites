<?php
$userRole   = $userRole   ?? 'admin';
$activePage = $activePage ?? '';

$menus = [
  'admin' => [
    'Overview' => [
      ['dashboard', 'Dashboard',              '/dashboard'],
    ],
    'Cinema' => [
      ['schedule',  'Showtimes',              '/schedule'],
      ['seats',     'Seat booking',           '/seats'],
    ],
    'Concessions' => [
      ['pos',       'Snack bar POS',          '/pos'],
      ['inventory', 'Inventory',              '/inventory'],
      ['bundles',   'Combos & bundles',       '/bundles'],
    ],
    'Finance' => [
      ['expenses',  'Utility expenses',       '/expenses'],
      ['sales',     'Sales reports',          '/reports/sales'],
      ['occupancy', 'Occupancy & peak hours', '/reports/occupancy'],
      ['financial', 'Net income',             '/reports/financial'],
    ],
    'System' => [
      ['audit',     'Audit logs',             '/audit'],
    ],
  ],
  'ticketing' => [
    'Cinema' => [
      ['schedule',  'Showtimes',              '/schedule'],
      ['seats',     'Seat booking',           '/seats'],
    ],
  ],
  'cashier' => [
    'Concessions' => [
      ['pos',       'Snack bar POS',          '/pos'],
      ['inventory', 'Stock levels',           '/inventory'],
    ],
  ],
];
$menu = $menus[$userRole] ?? [];
?>
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= BASE_URL ?>/">
    <span class="brand-mark">S</span>
    <span class="brand-name">ScreenBites</span>
  </a>

  <nav class="nav" aria-label="Main">
    <?php foreach ($menu as $group => $items): ?>
      <p class="nav-group"><?= htmlspecialchars($group) ?></p>
      <?php foreach ($items as [$key, $label, $path]): ?>
        <a class="nav-link<?= $activePage === $key ? ' is-active' : '' ?>"
           href="<?= BASE_URL . $path ?>"
           <?= $activePage === $key ? 'aria-current="page"' : '' ?>>
          <?= htmlspecialchars($label) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <a class="nav-link nav-logout" href="<?= BASE_URL ?>/logout">Log out</a>
</aside>
<div class="scrim" id="scrim"></div>