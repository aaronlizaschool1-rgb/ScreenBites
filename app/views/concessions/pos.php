<?php
/**
 * ScreenBites: Concessions Point-of-Sale (POS) View
 * 
 * Cashier register interface for concessions, promotional bundles,
 * held movie tickets checkout, and split payment tenders (FR8, FR9).
 * 
 * @var array<int, array<string, mixed>> $products
 * @var array<int, array<string, mixed>> $combos
 * @var float                            $taxRate
 * @var string                           $csrfToken
 * @var array<string, mixed>|null        $user
 */

$e = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$peso = "\u{20B1}";
$fmt = fn($n): string => number_format((float)$n, 2);

$products = is_array($products ?? null) ? $products : [];
$combos   = is_array($combos ?? null) ? $combos : [];
$taxRate  = (float)($taxRate ?? 0.12);
?>

<div class="page-head">
    <div>
        <h1>Snackbar Point-of-Sale</h1>
        <p>Process walk-in concession sales, promotional combos, and held movie tickets.</p>
    </div>
    <div class="pos-head-actions">
        <a href="<?= BASE_URL; ?>/schedules" class="btn btn-ghost pos-sched-link" title="Pick cinema screening to reserve seats">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                <rect x="2" y="2" width="20" height="20" rx="3"></rect>
                <path d="M7 2v20"></path><path d="M17 2v20"></path>
                <path d="M2 12h20"></path><path d="M2 7h5"></path><path d="M2 17h5"></path>
                <path d="M17 17h5"></path><path d="M17 7h5"></path>
            </svg>
            <span>Movie Schedules &amp; Seats</span>
        </a>
    </div>
</div>

<div class="pos-layout" id="posApp">
    <!-- LEFT: Concessions Catalog & Quick-Touch Register -->
    <section class="pos-catalog card" aria-label="Concessions Catalog">
        <div class="pos-toolbar">
            <div class="pos-search-box">
                <input type="search" id="posSearch" placeholder="Search snacks, drinks, combos..." aria-label="Search items">
            </div>
            <div class="pos-filter-tabs" role="tablist" aria-label="Catalog Categories">
                <button type="button" class="pos-tab active" data-category="all" role="tab" aria-selected="true">All Items</button>
                <button type="button" class="pos-tab" data-category="combos" role="tab" aria-selected="false">Combos &amp; Deals</button>
                <button type="button" class="pos-tab" data-category="Popcorn" role="tab" aria-selected="false">Popcorn</button>
                <button type="button" class="pos-tab" data-category="Beverage" role="tab" aria-selected="false">Beverages</button>
                <button type="button" class="pos-tab" data-category="Snacks" role="tab" aria-selected="false">Snacks</button>
                <button type="button" class="pos-tab" data-category="Candy" role="tab" aria-selected="false">Candy</button>
            </div>
        </div>

        <!-- Section: Combos & Bundles -->
        <div class="pos-section" id="combosSection">
            <h2 class="pos-section-title">Promotional Bundles &amp; Combos</h2>
            <div class="pos-grid" id="combosGrid">
                <?php if (empty($combos)): ?>
                    <p class="empty-note">No active combo packages available.</p>
                <?php else: ?>
                    <?php foreach ($combos as $combo): 
                        $cId = (int)$combo['combo_id'];
                        $cPrice = (float)$combo['combo_price'];
                        $indPrice = (float)($combo['individual_sum_price'] ?? 0);
                        $savings = max(0, $indPrice - $cPrice);
                    ?>
                        <article class="pos-item-card combo-card" 
                                 data-category="combos" 
                                 data-name="<?= $e(strtolower($combo['combo_name'] . ' ' . ($combo['description'] ?? ''))); ?>">
                            <div class="pos-card-badge combo-badge">Combo Deal</div>
                            <div class="pos-card-body">
                                <h3 class="pos-card-title"><?= $e($combo['combo_name']); ?></h3>
                                <p class="pos-card-desc"><?= $e($combo['item_breakdown'] ?? $combo['description'] ?? ''); ?></p>
                                <div class="pos-card-pricing">
                                    <span class="pos-price"><?= $peso . $fmt($cPrice); ?></span>
                                    <?php if ($indPrice > $cPrice): ?>
                                        <del class="pos-old-price"><?= $peso . $fmt($indPrice); ?></del>
                                        <span class="pos-savings-tag">Save <?= $peso . $fmt($savings); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" 
                                    class="pos-add-btn btn" 
                                    data-action="add-combo"
                                    data-id="<?= $cId; ?>"
                                    data-name="<?= $e($combo['combo_name']); ?>"
                                    data-price="<?= $cPrice; ?>">
                                + Add Combo
                            </button>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section: A La Carte Products -->
        <div class="pos-section" id="productsSection">
            <h2 class="pos-section-title">Concessions Menu</h2>
            <div class="pos-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                    <p class="empty-note">No products configured in inventory.</p>
                <?php else: ?>
                    <?php foreach ($products as $p): 
                        $pId = (int)$p['product_id'];
                        $stock = (int)$p['current_stock'];
                        $reorder = (int)$p['reorder_level'];
                        $isLow = (bool)($p['is_low_stock'] ?? ($stock <= $reorder));
                        $isOut = ($stock <= 0);
                        $cat = (string)$p['category'];
                        $price = (float)$p['retail_price'];
                    ?>
                        <article class="pos-item-card <?= $isOut ? 'is-out-of-stock' : ($isLow ? 'is-low-stock' : ''); ?>"
                                 data-category="<?= $e($cat); ?>"
                                 data-name="<?= $e(strtolower($p['product_name'] . ' ' . $cat)); ?>">
                            <div class="pos-card-top">
                                <span class="pos-category-tag"><?= $e($cat); ?></span>
                                <?php if ($isOut): ?>
                                    <span class="pos-stock-badge out-badge">Out of Stock</span>
                                <?php elseif ($isLow): ?>
                                    <span class="pos-stock-badge low-badge">Only <?= $stock; ?> left</span>
                                <?php else: ?>
                                    <span class="pos-stock-badge in-badge"><?= $stock; ?> in stock</span>
                                <?php endif; ?>
                            </div>
                            <div class="pos-card-body">
                                <h3 class="pos-card-title"><?= $e($p['product_name']); ?></h3>
                                <div class="pos-card-pricing">
                                    <span class="pos-price"><?= $peso . $fmt($price); ?></span>
                                </div>
                            </div>
                            <button type="button" 
                                    class="pos-add-btn btn <?= $isOut ? 'btn-ghost' : ''; ?>"
                                    data-action="add-product"
                                    data-id="<?= $pId; ?>"
                                    data-name="<?= $e($p['product_name']); ?>"
                                    data-price="<?= $price; ?>"
                                    data-stock="<?= $stock; ?>"
                                    <?= $isOut ? 'disabled aria-disabled="true"' : ''; ?>>
                                <?= $isOut ? 'Sold Out' : '+ Add to Order'; ?>
                            </button>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- RIGHT: Guest Cart & Modern Checkout Panel -->
    <aside class="pos-cart-panel card" id="posCartPanel" aria-label="Guest Order &amp; Checkout">
        <div class="pos-cart-header">
            <div>
                <h2 class="card-title">Current Order</h2>
                <span class="pos-order-badge" id="orderTypeBadge">Walk-in Concessions</span>
            </div>
            <button type="button" class="btn btn-ghost btn-sm btn-clear-cart" id="clearCartBtn" title="Empty order cart" disabled>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <span>Clear</span>
            </button>
        </div>

        <!-- Cinema Held Tickets Container -->
        <div class="cart-section" id="ticketsSection" style="display: none;">
            <div class="cart-section-head">
                <span class="cart-section-title">Reserved Tickets (<span id="ticketCount">0</span>)</span>
                <span class="cart-ticket-timer" id="ticketTimer" title="Held seats timeout">--:--</span>
            </div>
            <div class="cart-tickets-list" id="cartTicketsList">
                <!-- Dynamically populated from sessionStorage.screenbites.ticketCart -->
            </div>
        </div>

        <!-- Concessions Items List -->
        <div class="cart-section" id="concessionsSection">
            <div class="cart-section-head">
                <span class="cart-section-title">Order Items</span>
            </div>
            <div class="cart-items-list" id="cartConcessionsList">
                <!-- Populated dynamically -->
            </div>
            
            <!-- Modern Empty State -->
            <div class="empty-cart-state" id="cartEmptyMsg">
                <div class="empty-cart-icon" aria-hidden="true">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </div>
                <h4>Your order is empty</h4>
                <p>Click <strong>+ Add to Order</strong> on any concession or combo to begin.</p>
            </div>
        </div>

        <!-- Financial Breakdown -->
        <div class="cart-breakdown" id="cartBreakdown">
            <div class="cart-breakdown-row" id="rowTicketsSubtotal" style="display: none;">
                <span>Tickets Total</span>
                <strong id="ticketsSubtotal"><?= $peso; ?>0.00</strong>
            </div>
            <div class="cart-breakdown-row">
                <span>Concessions Total</span>
                <strong id="concessionsSubtotal"><?= $peso; ?>0.00</strong>
            </div>
            <div class="cart-breakdown-row">
                <span>VAT-Exclusive Subtotal</span>
                <span id="vatExclusiveSubtotal"><?= $peso; ?>0.00</span>
            </div>
            <div class="cart-breakdown-row">
                <span>VAT (12% Included)</span>
                <span id="vatAmount"><?= $peso; ?>0.00</span>
            </div>
            <div class="cart-breakdown-row cart-total-row">
                <span>Total Amount Due</span>
                <strong class="cart-grand-total" id="grandTotal"><?= $peso; ?>0.00</strong>
            </div>
        </div>

        <!-- Payment Section (Clean Single-Payment by default, Split on demand) -->
        <div class="cart-payment-section" id="cartPaymentSection">
            <div class="payment-section-header">
                <h3 class="payment-title">Payment Method</h3>
                <button type="button" class="btn-split-toggle" id="splitToggleBtn" title="Split payment across multiple payment methods">
                    <span id="splitToggleText">⇄ Split Payment</span>
                </button>
            </div>

            <!-- Single or Split Payment Entry Form -->
            <div class="tender-entry-form">
                <div class="tender-method-group">
                    <button type="button" class="tender-method-btn active" data-method="Cash">
                        <span class="method-icon">💵</span>
                        <span>Cash</span>
                    </button>
                    <button type="button" class="tender-method-btn" data-method="E-Wallet">
                        <span class="method-icon">📱</span>
                        <span>GCash / Maya</span>
                    </button>
                    <button type="button" class="tender-method-btn" data-method="Card">
                        <span class="method-icon">💳</span>
                        <span>Card</span>
                    </button>
                </div>

                <div class="tender-inputs">
                    <div class="tender-field" id="cashTenderField">
                        <label for="tenderAmount" id="tenderAmountLabel">Cash Received (<?= $peso; ?>)</label>
                        <input type="number" id="tenderAmount" step="0.01" min="0.01" placeholder="0.00" inputmode="decimal">
                    </div>

                    <div class="tender-field" id="referenceField" style="display: none;">
                        <label for="tenderRef" id="tenderRefLabel">Reference / Approval #</label>
                        <input type="text" id="tenderRef" placeholder="e.g. GCash Ref # or Card Auth">
                    </div>
                </div>

                <!-- Quick Cash Presets (for fast 1-click cash handling) -->
                <div class="quick-cash-row" id="quickCashRow">
                    <button type="button" class="quick-cash-btn" data-quick="exact">Exact</button>
                    <button type="button" class="quick-cash-btn" data-quick="100">+100</button>
                    <button type="button" class="quick-cash-btn" data-quick="200">+200</button>
                    <button type="button" class="quick-cash-btn" data-quick="500">+500</button>
                    <button type="button" class="quick-cash-btn" data-quick="1000">1,000</button>
                </div>

                <!-- Add Tender Button (Shown only when in Split Payment mode) -->
                <button type="button" class="btn btn-ghost tender-add-btn" id="addTenderBtn" style="display: none;">
                    + Add This Payment Tender
                </button>
            </div>

            <!-- Added Split Tenders List (Shown only when split mode or tenders recorded) -->
            <div class="tenders-list" id="tendersList" style="display: none;">
                <!-- Populated as cashier records split payments -->
            </div>

            <!-- Payment Balance & Change Due Tracker -->
            <div class="payment-summary-box" id="paymentSummaryBox">
                <div class="payment-summary-row" id="totalTenderedRow">
                    <span id="totalTenderedLabel">Amount Received:</span>
                    <strong id="totalTendered"><?= $peso; ?>0.00</strong>
                </div>
                <div class="payment-summary-row" id="remainingRow" style="display: none;">
                    <span>Remaining Balance:</span>
                    <strong class="text-danger" id="remainingBalance"><?= $peso; ?>0.00</strong>
                </div>
                <div class="payment-summary-row change-row" id="changeRow">
                    <span>Change Due:</span>
                    <strong class="text-success" id="changeDue"><?= $peso; ?>0.00</strong>
                </div>
            </div>

            <!-- Primary Action Button: 1-Click Pay & Order Completion -->
            <button type="button" class="btn btn-primary btn-block checkout-submit-btn" id="submitOrderBtn" disabled>
                Select Items to Order
            </button>
            <p class="checkout-msg" id="checkoutMsg" role="status" aria-live="polite"></p>
        </div>
    </aside>
</div>

<!-- Modal: Transaction Success & Receipt Action -->
<dialog class="sched-dialog" id="orderSuccessModal" aria-labelledby="modalTitle">
    <div class="success-modal-content">
        <div class="success-icon" aria-hidden="true">&#10003;</div>
        <h2 id="modalTitle">Transaction Completed!</h2>
        <p class="order-ref-code">Order Number: <strong id="successOrderNumber">ORD-XXXXXX</strong></p>
        
        <div class="success-summary">
            <div class="success-row">
                <span>Grand Total Paid:</span>
                <strong id="successTotalPaid"><?= $peso; ?>0.00</strong>
            </div>
            <div class="success-row" id="successChangeRow" style="display: none;">
                <span>Change Returned:</span>
                <strong class="text-success" id="successChangeDue"><?= $peso; ?>0.00</strong>
            </div>
        </div>

        <div class="sched-actions" style="justify-content: center; margin-top: 1.5rem; gap: .75rem;">
            <a href="#" id="viewReceiptBtn" class="btn" target="_blank" rel="noopener">
                Print Official Receipt
            </a>
            <button type="button" class="btn btn-ghost" id="nextSaleBtn">
                Start Next Sale
            </button>
        </div>
    </div>
</dialog>

<script>
    window.PosConfig = {
        baseUrl: <?= json_encode(BASE_URL); ?>,
        csrfToken: <?= json_encode($csrfToken); ?>,
        taxRate: <?= (float)$taxRate; ?>,
        currencySymbol: <?= json_encode($peso); ?>
    };
</script>
<script src="<?= BASE_URL; ?>/assets/js/pos-cart.js"></script>
