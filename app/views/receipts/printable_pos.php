<?php
/**
 * ScreenBites: Consolidated Printable Thermal Receipt View (FR10)
 * 
 * Standalone thermal POS receipt rendering cinema tickets, itemized
 * concession purchases, 12% VAT calculations, and split-tender payment proofs.
 * 
 * @var array<string, mixed>             $order
 * @var array<int, array<string, mixed>> $tickets
 * @var array<int, array<string, mixed>> $items
 * @var array<int, array<string, mixed>> $payments
 */

$e = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$peso = "\u{20B1}";
$fmt = fn($n): string => number_format((float)$n, 2);

$order    = is_array($order ?? null) ? $order : [];
$tickets  = is_array($tickets ?? null) ? $tickets : [];
$items    = is_array($items ?? null) ? $items : [];
$payments = is_array($payments ?? null) ? $payments : [];

$subtotal   = (float)($order['subtotal_amount'] ?? 0);
$taxAmount  = (float)($order['tax_amount'] ?? 0);
$totalGross = (float)($order['total_amount'] ?? ($subtotal + $taxAmount));

// Calculate payment tenders summary
$totalPaid = 0.00;
$totalChange = 0.00;
foreach ($payments as $p) {
    $totalPaid += (float)$p['amount_paid'];
    $totalChange += (float)$p['change_returned'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $e($order['order_number'] ?? 'RECEIPT'); ?> | ScreenBites</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL; ?>/assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Figtree:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --receipt-width: 340px;
            --receipt-bg: #ffffff;
            --receipt-ink: #111111;
            --receipt-muted: #555555;
            --receipt-line: #000000;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 2rem 1rem;
            background: #eef0f6;
            color: var(--receipt-ink);
            font-family: 'Space Mono', monospace, Consolas, Courier, sans-serif;
            font-size: 12.5px;
            line-height: 1.4;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        /* Screen Action Toolbar (Hidden during printing) */
        .receipt-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .85rem;
            margin-bottom: 1.5rem;
            width: 100%;
            max-width: var(--receipt-width);
        }

        .btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            height: 42px;
            padding: 0 1rem;
            border-radius: 8px;
            font-family: 'Figtree', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: all .15s ease;
            box-sizing: border-box;
        }

        .btn-print {
            background: #2b2d7a;
            color: #ffffff;
            border: 1.5px solid #2b2d7a;
        }

        .btn-print:hover {
            background: #1d1f58;
            border-color: #1d1f58;
        }

        .btn-pos {
            background: #ffffff;
            color: #2b2d7a;
            border: 1.5px solid #2b2d7a;
        }

        .btn-pos:hover {
            background: #f0f2fa;
        }

        .btn-refund {
            background: #fef2f2;
            color: #dc2626;
            border: 1.5px solid #f87171;
        }

        .btn-refund:hover {
            background: #fee2e2;
            border-color: #ef4444;
        }

        .btn-refunded-badge {
            background: #fee2e2;
            color: #991b1b;
            border: 1.5px solid #f87171;
            cursor: default;
        }

        /* Refund Stamp & Watermark */
        .refund-stamp-banner {
            border: 2px dashed #dc2626;
            background: #fef2f2;
            color: #dc2626;
            text-align: center;
            padding: .65rem .5rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }

        .refund-stamp-text {
            font-size: 14.5px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .refund-stamp-sub {
            font-size: 10px;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .watermark-void {
            position: absolute;
            top: 48%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-28deg);
            font-size: 44px;
            font-weight: 800;
            color: rgba(220, 38, 38, 0.16);
            border: 4px solid rgba(220, 38, 38, 0.22);
            padding: .4rem 1.25rem;
            pointer-events: none;
            user-select: none;
            letter-spacing: .12em;
            text-transform: uppercase;
            white-space: nowrap;
            border-radius: 8px;
            z-index: 10;
        }

        /* Modal styling */
        .sched-dialog {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            max-width: 440px;
            width: 90vw;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .sched-dialog::backdrop {
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(2px);
        }

        .sched-dialog h2 {
            margin: 0 0 .35rem;
            font-size: 17px;
            font-family: 'Figtree', sans-serif;
        }

        .sched-dialog p {
            margin: 0 0 1rem;
            font-size: 12px;
            color: #64748b;
            font-family: 'Figtree', sans-serif;
            line-height: 1.45;
        }

        .sched-field {
            margin-bottom: 1rem;
            font-family: 'Figtree', sans-serif;
        }

        .sched-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: .35rem;
            color: #334155;
        }

        .sched-field select, .sched-field input {
            width: 100%;
            padding: .6rem .75rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            box-sizing: border-box;
            font-family: inherit;
        }

        .sched-actions {
            display: flex;
            gap: .5rem;
            justify-content: flex-end;
            margin-top: 1.25rem;
        }

        /* Thermal Slip Styling */
        .thermal-slip {
            width: 100%;
            max-width: var(--receipt-width);
            background: var(--receipt-bg);
            padding: 1.75rem 1.4rem 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
            position: relative;
        }

        /* Perforation jagged edges */
        .thermal-slip::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, transparent 75%, var(--receipt-bg) 75%) 0 0,
                        linear-gradient(225deg, transparent 75%, var(--receipt-bg) 75%) 0 0;
            background-size: 8px 8px;
            margin-top: -4px;
        }

        .slip-header {
            text-align: center;
            margin-bottom: 1rem;
        }

        .slip-logo {
            width: 46px;
            height: 46px;
            border-radius: 8px;
            margin: 0 auto .5rem;
            display: block;
        }

        .slip-brand {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .02em;
            margin: 0 0 .25rem;
            text-transform: uppercase;
        }

        .slip-subtitle {
            font-size: 11px;
            margin: 0;
            color: var(--receipt-muted);
            text-transform: uppercase;
        }

        .slip-tin {
            font-size: 10.5px;
            margin: .25rem 0 0;
            color: var(--receipt-muted);
        }

        .divider {
            border-top: 1px dashed var(--receipt-line);
            margin: .85rem 0;
        }

        .divider-double {
            border-top: 2px dashed var(--receipt-line);
            margin: .85rem 0;
        }

        .meta-table {
            width: 100%;
            font-size: 11.5px;
            margin-bottom: .5rem;
        }

        .meta-table td {
            padding: .15rem 0;
            vertical-align: top;
        }

        .meta-table td:last-child {
            text-align: right;
            font-weight: 700;
        }

        .section-label {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin: .85rem 0 .4rem;
            display: block;
            text-align: center;
        }

        /* Ticket Item Block */
        .ticket-item {
            margin-bottom: .6rem;
            padding-bottom: .5rem;
            border-bottom: 1px dotted #ccc;
        }

        .ticket-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .ticket-title {
            font-weight: 700;
            font-size: 12.5px;
            display: flex;
            justify-content: space-between;
        }

        .ticket-details {
            font-size: 11px;
            color: var(--receipt-muted);
            margin-top: .15rem;
        }

        .ticket-seat-highlight {
            font-weight: 700;
            color: var(--receipt-ink);
            display: block;
            margin-top: .15rem;
        }

        /* Concessions Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }

        .items-table th {
            text-align: left;
            border-bottom: 1px dashed var(--receipt-line);
            padding: .3rem 0;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
        }

        .items-table td {
            padding: .35rem 0;
            vertical-align: top;
        }

        .items-table td.num, .items-table th.num {
            text-align: right;
        }

        .items-table td.qty {
            width: 30px;
        }

        /* Totals Block */
        .totals-table {
            width: 100%;
            font-size: 12px;
            margin-top: .5rem;
        }

        .totals-table td {
            padding: .2rem 0;
        }

        .totals-table td:last-child {
            text-align: right;
        }

        .grand-total-row td {
            font-size: 14.5px;
            font-weight: 700;
            padding-top: .4rem;
            padding-bottom: .4rem;
        }

        /* Payments Block */
        .payments-table {
            width: 100%;
            font-size: 11.5px;
            margin-top: .3rem;
        }

        .payments-table td {
            padding: .15rem 0;
        }

        .payments-table td:last-child {
            text-align: right;
            font-weight: 700;
        }

        /* Footer */
        .slip-footer {
            text-align: center;
            margin-top: 1.25rem;
            font-size: 11px;
            color: var(--receipt-muted);
        }

        .barcode-sim {
            font-size: 26px;
            letter-spacing: .25em;
            margin: .6rem 0 .3rem;
            color: var(--receipt-line);
            font-family: 'Space Mono', monospace;
            text-align: center;
            overflow: hidden;
            user-select: none;
        }

        .official-tag {
            font-size: 10px;
            text-transform: uppercase;
            margin-top: .5rem;
            display: block;
            letter-spacing: .05em;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
                margin: 0;
            }

            .receipt-actions {
                display: none !important;
            }

            .thermal-slip {
                box-shadow: none;
                max-width: 100%;
                width: 100%;
                padding: 0;
                margin: 0;
            }

            .thermal-slip::before {
                display: none;
            }
        }
    </style>
</head>
<body>

<?php
$isRefunded = (($order['order_status'] ?? '') === 'Refunded');
$canRefund  = \Core\Auth::hasRole([\ROLE_ADMIN, \ROLE_TICKETING]);
?>
    <!-- On-screen Navigation Actions -->
    <div class="receipt-actions">
        <button type="button" class="btn btn-print" onclick="window.print();">
            &#128424; Print Receipt
        </button>
        <a href="<?= BASE_URL; ?>/pos" class="btn btn-pos">
            &larr; POS Counter
        </a>
        <?php if ($isRefunded): ?>
            <span class="btn btn-refunded-badge">&#10006; Void / Refunded</span>
        <?php elseif ($canRefund): ?>
            <button type="button" class="btn btn-refund" id="openRefundModalBtn">
                &#8634; Refund Order
            </button>
        <?php endif; ?>
    </div>

    <!-- Thermal Paper Receipt Container -->
    <main class="thermal-slip" aria-label="Official Receipt">
        <?php if ($isRefunded): ?>
            <div class="refund-stamp-banner">
                <div class="refund-stamp-text">*** VOID / REFUNDED ***</div>
                <div class="refund-stamp-sub">Tickets Cancelled &bull; Concessions Restocked &bull; Seats Released</div>
            </div>
            <div class="watermark-void" aria-hidden="true">VOID / REFUNDED</div>
        <?php endif; ?>

        <header class="slip-header">
            <img src="<?= BASE_URL; ?>/assets/img/logo.png" alt="ScreenBites" class="slip-logo">
            <h1 class="slip-brand">ScreenBites Cinema</h1>
            <p class="slip-subtitle">Cinema Reservation &amp; Concessions POS</p>
            <p class="slip-tin">VAT REG TIN: 009-876-543-000<br>Mall of Asia Complex, Pasay City</p>
        </header>

        <div class="divider"></div>

        <table class="meta-table">
            <tr>
                <td>RECEIPT #:</td>
                <td><strong style="<?= $isRefunded ? 'color:#dc2626;' : ''; ?>"><?= $e($order['order_number'] ?? 'N/A'); ?></strong></td>
            </tr>
            <tr>
                <td>DATE &amp; TIME:</td>
                <td><?= date('Y-m-d h:i:s A', strtotime($order['created_at'] ?? 'now')); ?></td>
            </tr>
            <tr>
                <td>CASHIER:</td>
                <td><?= $e($order['cashier_name'] ?? 'Staff Cashier'); ?></td>
            </tr>
            <tr>
                <td>STATUS:</td>
                <td>
                    <?php if ($isRefunded): ?>
                        <strong style="color: #dc2626;">REFUNDED / VOID</strong>
                    <?php else: ?>
                        <strong><?= $e($order['order_status'] ?? 'COMPLETED'); ?></strong>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- Cinema Tickets Section (If Any) -->
        <?php if (!empty($tickets)): ?>
            <div class="divider"></div>
            <span class="section-label">Cinema Admission Tickets</span>
            
            <div class="tickets-container">
                <?php foreach ($tickets as $t): 
                    $tPrice = (float)$t['price_charged'];
                    $sDate = !empty($t['screening_date']) ? date('M d, Y', strtotime($t['screening_date'])) : '';
                    $sTime = !empty($t['start_time']) ? date('g:i A', strtotime($t['start_time'])) : '';
                ?>
                    <div class="ticket-item">
                        <div class="ticket-title">
                            <span><?= $e($t['movie_title']); ?> [<?= $e($t['movie_rating'] ?? 'PG'); ?>]</span>
                            <span><?= $peso . $fmt($tPrice); ?></span>
                        </div>
                        <div class="ticket-details">
                            <span><?= $e($t['hall_name']); ?> &bull; <?= $sDate; ?> at <?= $sTime; ?></span>
                            <span class="ticket-seat-highlight">
                                ROW <?= $e($t['seat_row']); ?>, SEAT <?= (int)$t['seat_number']; ?> (<?= $e($t['tier_name']); ?>)
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Concessions Section (If Any) -->
        <?php if (!empty($items)): ?>
            <div class="divider"></div>
            <span class="section-label">Concessions &amp; Snacks</span>

            <table class="items-table">
                <thead>
                    <tr>
                        <th class="qty">QTY</th>
                        <th>ITEM</th>
                        <th class="num">PRICE</th>
                        <th class="num">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): 
                        $qty = (int)$item['quantity'];
                        $uPrice = (float)$item['unit_price_charged'];
                        $lTotal = (float)($item['total_line_price'] ?? ($qty * $uPrice));
                    ?>
                        <tr>
                            <td class="qty"><?= $qty; ?>x</td>
                            <td>
                                <?= $e($item['item_name']); ?>
                                <?php if (($item['category'] ?? '') === 'Bundle'): ?>
                                    <small style="display:block; font-size:10px; color:#666;">(Promotional Bundle)</small>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= $peso . $fmt($uPrice); ?></td>
                            <td class="num"><?= $peso . $fmt($lTotal); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Financial Summary Breakdown -->
        <div class="divider-double"></div>

        <table class="totals-table">
            <tr>
                <td>VAT-Exclusive Subtotal:</td>
                <td><?= $peso . $fmt($subtotal); ?></td>
            </tr>
            <tr>
                <td>VAT (12% Included):</td>
                <td><?= $peso . $fmt($taxAmount); ?></td>
            </tr>
            <tr class="grand-total-row">
                <td><strong>GRAND TOTAL:</strong></td>
                <td><strong><?= $peso . $fmt($totalGross); ?></strong></td>
            </tr>
        </table>

        <!-- Payments & Split Tenders -->
        <div class="divider"></div>
        <span class="section-label">Payment Breakdown</span>

        <table class="payments-table">
            <?php foreach ($payments as $p): 
                $method = (string)$p['payment_method'];
                $amt = (float)$p['amount_paid'];
                $ref = !empty($p['reference_number']) ? ' (Ref: ' . $e($p['reference_number']) . ')' : '';
            ?>
                <tr>
                    <td>TENDER (<?= $e($method . $ref); ?>):</td>
                    <td><?= $peso . $fmt($amt); ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if ($totalChange > 0): ?>
                <tr>
                    <td>CHANGE RETURNED:</td>
                    <td><strong><?= $peso . $fmt($totalChange); ?></strong></td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- Digital Barcode & Footer -->
        <div class="divider"></div>

        <footer class="slip-footer">
            <div class="barcode-sim" aria-hidden="true">||| | |||| | |||||| || | |||</div>
            <p style="margin: .25rem 0 0; font-size: 10px;"><?= $e($order['order_number'] ?? ''); ?></p>
            <p style="margin: .5rem 0 0;">THANK YOU FOR CHOOSING SCREENBITES!<br>ENJOY YOUR MOVIE &amp; SNACKS!</p>
            <span class="official-tag">*** Official Digital Sales Invoice ***</span>
        </footer>
    </main>

    <?php if (!$isRefunded && $canRefund): ?>
        <!-- Refund Confirmation Modal -->
        <dialog class="sched-dialog" id="refundSlipModal">
            <form method="POST" action="<?= BASE_URL; ?>/checkout/refund">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Auth::csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="order_number" value="<?= $e($order['order_number'] ?? ''); ?>">

                <h2 style="color: #dc2626;">Authorize Order Refund</h2>
                <p>Are you sure you want to refund Order <strong><?= $e($order['order_number'] ?? ''); ?></strong>? This will void all cinema tickets, free up reserved seats on the seating matrix, and restock items into inventory.</p>

                <div class="sched-field">
                    <label for="refundSlipReason">Reason for Refund *</label>
                    <select id="refundSlipReason" name="reason" required>
                        <option value="Customer Cancellation Request">Customer Cancellation Request</option>
                        <option value="Screening Schedule Rescheduled">Screening Schedule Rescheduled</option>
                        <option value="Incorrect Seat / Movie Selection">Incorrect Seat / Movie Selection</option>
                        <option value="POS Billing Error">POS Billing Error</option>
                        <option value="Operational Facility Issue">Operational Facility Issue</option>
                    </select>
                </div>

                <div class="sched-actions">
                    <button type="button" class="btn btn-pos" id="closeRefundModalBtn" style="height:38px;">Cancel</button>
                    <button type="submit" class="btn btn-refund" style="height:38px; background:#dc2626; color:#fff; border-color:#dc2626;">Confirm Full Refund</button>
                </div>
            </form>
        </dialog>

        <script>
        (function() {
            var openBtn = document.getElementById('openRefundModalBtn');
            var closeBtn = document.getElementById('closeRefundModalBtn');
            var modal = document.getElementById('refundSlipModal');

            if (openBtn && modal) {
                openBtn.addEventListener('click', function() {
                    if (typeof modal.showModal === 'function') {
                        modal.showModal();
                    }
                });
            }

            if (closeBtn && modal) {
                closeBtn.addEventListener('click', function() {
                    modal.close();
                });
            }
        })();
        </script>
    <?php endif; ?>

</body>
</html>
