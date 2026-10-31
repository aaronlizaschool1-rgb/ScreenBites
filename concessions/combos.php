<?php
/**
 * ScreenBites - Combo Packages Management
 * Procedural module modeled after PedalWorks-Dynamics
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Manage Concession Bundles & Combos';
require_once dirname(__DIR__) . '/includes/header.php';

// Fetch combos with their constituent items
$combosSql = "SELECT c.*, 
                     COUNT(ci.product_id) AS total_items_count
              FROM combos c
              LEFT JOIN combo_items ci ON c.combo_id = ci.combo_id
              GROUP BY c.combo_id
              ORDER BY c.is_active DESC, c.combo_id ASC";
$combosRes = mysqli_query($conn, $combosSql);
?>

<div class="sb-page-header">
    <div>
        <h1>Concession Bundles &amp; Combos</h1>
        <p>Configure multi-item discount packages and snack bar pairings.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">&larr; Concessions Catalog</a>
        <a href="<?= BASE_URL; ?>/concessions/create_combo.php" class="btn btn-primary">+ Create New Bundle</a>
    </div>
</div>

<div class="sb-card">
    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th>Bundle Name</th>
                    <th>Included Concessions</th>
                    <th>Retail Price</th>
                    <th>Constituent Availability</th>
                    <th>Status</th>
                    <th style="text-align: right; width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($combosRes && mysqli_num_rows($combosRes) > 0): ?>
                    <?php while ($c = mysqli_fetch_assoc($combosRes)): 
                        $cId = (int)$c['combo_id'];

                        // Fetch constituent items with product current stock
                        $itemsSql = "SELECT ci.quantity, p.product_name, p.current_stock
                                     FROM combo_items ci
                                     INNER JOIN products p ON ci.product_id = p.product_id
                                     WHERE ci.combo_id = {$cId}";
                        $itemsRes = mysqli_query($conn, $itemsSql);
                        $parts = [];
                        $maxPossible = 9999;
                        while ($item = mysqli_fetch_assoc($itemsRes)) {
                            $parts[] = (int)$item['quantity'] . "x " . sanitize($item['product_name']);
                            $stock = (int)$item['current_stock'];
                            $req = (int)$item['quantity'];
                            $avail = ($req > 0) ? intdiv($stock, $req) : 9999;
                            if ($avail < $maxPossible) {
                                $maxPossible = $avail;
                            }
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($c['combo_name']); ?></strong>
                                <?php if (!empty($c['description'])): ?>
                                    <small style="display: block; color: var(--c-muted, #5d6180); font-size: 0.8rem; margin-top: 0.2rem;">
                                        <?= sanitize($c['description']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($parts)): ?>
                                    <span style="font-size: 0.88rem; color: var(--c-ink, #1d1f3a); font-weight: 500;">
                                        <?= implode(' &bull; ', $parts); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--c-muted, #5d6180); font-style: italic;">No items assigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; color: var(--accent-gold, #f59e0b);">
                                <?= format_currency($c['combo_price']); ?>
                            </td>
                            <td>
                                <?php if ($maxPossible > 0): ?>
                                    <span style="color: #10b981; font-weight: 600; font-size: 0.85rem;">
                                        ✓ <?= $maxPossible; ?> bundles can be fulfilled
                                    </span>
                                <?php else: ?>
                                    <span style="color: #ef4444; font-weight: 600; font-size: 0.85rem;">
                                        ⚠️ Out of stock (constituent empty)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$c['is_active'] === 1): ?>
                                    <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Active</span>
                                <?php else: ?>
                                    <span style="background: rgba(156, 163, 175, 0.15); color: #9ca3af; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="<?= BASE_URL; ?>/concessions/edit_combo.php?id=<?= $cId; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                <a href="<?= BASE_URL; ?>/concessions/delete_combo.php?id=<?= $cId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete or deactivate this combo bundle?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            No combo bundles registered yet. Click "+ Create New Bundle" to define one.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
