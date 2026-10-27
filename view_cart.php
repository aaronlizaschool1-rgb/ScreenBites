<?php
/**
 * ScreenBites - View Shopping Cart
 * Modeled after itim211-2b-shop/view_cart.php
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/includes/header.php';

$cart = $_SESSION['cart'] ?? [];
$grandTotal = 0.00;
?>

<div class="sb-page-header">
    <div>
        <h1>Your Shopping Cart</h1>
        <p>Review your selected movie tickets and snack concessions before checkout.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= BASE_URL; ?>/index.php" class="btn btn-secondary">&larr; Movies</a>
        <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary">&larr; Concessions</a>
    </div>
</div>

<?php if (empty($cart)): ?>
    <div class="sb-card" style="text-align: center; padding: 3rem 1rem;">
        <p style="font-size: 1.2rem; color: var(--text-muted); margin-bottom: 1.5rem;">Your cart is currently empty.</p>
        <a href="<?= BASE_URL; ?>/index.php" class="btn btn-primary">Browse Now Showing Movies</a>
        <a href="<?= BASE_URL; ?>/concessions/index.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Order Snacks</a>
    </div>
<?php else: ?>
    <form method="POST" action="<?= BASE_URL; ?>/cart_update.php">
        <input type="hidden" name="type" value="update">

        <div class="sb-card">
            <div class="sb-table-wrap">
                <table class="sb-table">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th>Category / Screening</th>
                            <th style="text-align: right;">Price</th>
                            <th style="text-align: center; width: 120px;">Qty</th>
                            <th style="text-align: right;">Subtotal</th>
                            <th style="text-align: center; width: 80px;">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart as $key => $item):
                            $price = (float)$item['price'];
                            $qty = (int)$item['qty'];
                            $lineTotal = $price * $qty;
                            $grandTotal += $lineTotal;
                            $isTicket = ($item['type'] === 'ticket');
                        ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($item['name']); ?></strong>
                                </td>
                                <td>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">
                                        <?= sanitize($item['details'] ?? 'General Item'); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <?= format_currency($price); ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($isTicket): ?>
                                        <span style="font-weight: 600;">1</span>
                                    <?php else: ?>
                                        <input type="number" 
                                               name="product_qty[<?= $key; ?>]" 
                                               value="<?= $qty; ?>" 
                                               min="1" 
                                               max="<?= $item['max_qty'] ?? 99; ?>" 
                                               class="form-control" 
                                               style="width: 70px; text-align: center; display: inline-block; padding: 0.3rem;">
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--accent-gold);">
                                    <?= format_currency($lineTotal); ?>
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="remove_code[]" value="<?= $key; ?>" title="Check to remove item">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $subtotalVatEx = round($grandTotal / (1 + TAX_RATE), 2);
            $vatAmount     = round($grandTotal - $subtotalVatEx, 2);
            ?>

            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <button type="submit" class="btn btn-secondary">Update Cart</button>
                    <small style="color: var(--text-muted); margin-left: 0.5rem;">Check boxes above to remove items.</small>
                </div>

                <div style="min-width: 250px; background: #0e1017; padding: 1.25rem; border-radius: 8px; border: 1px solid var(--card-border);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                        <span>Vatable Subtotal:</span>
                        <span><?= format_currency($subtotalVatEx); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                        <span>VAT (12% Included):</span>
                        <span><?= format_currency($vatAmount); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 700; color: #ffffff; border-top: 1px solid var(--card-border); padding-top: 0.75rem;">
                        <span>Grand Total:</span>
                        <span style="color: var(--accent-gold);"><?= format_currency($grandTotal); ?></span>
                    </div>

                    <a href="<?= BASE_URL; ?>/checkout.php" class="btn btn-primary" style="width: 100%; margin-top: 1rem; text-align: center;">
                        Proceed to Checkout &rarr;
                    </a>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
