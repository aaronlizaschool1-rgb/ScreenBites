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
            gap: .75rem;
            margin-bottom: 1.5rem;
            width: 100%;
            max-width: var(--receipt-width);
        }

        .btn {
            flex: 1;
            display: inline-block;
            text-align: center;
            padding: .65rem .85rem;
            border-radius: 8px;
            font-family: 'Figtree', sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: background .15s ease;
        }

        .btn-print {
            background: #2b2d7a;
            color: #ffffff;
        }

        .btn-print:hover {
            background: #1d1f58;
        }

        .btn-pos {
            background: #ffffff;
            color: #2b2d7a;
            border: 1.5px solid #2b2d7a;
        }

        .btn-pos:hover {
            background: #f0f2fa;
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

    <!-- On-screen Navigation Actions -->
    <div class="receipt-actions">
        <button type="button" class="btn btn-print" onclick="window.print();">
            &#128424; Print Receipt (FR10)
        </button>
        <a href="<?= BASE_URL; ?>/pos" class="btn btn-pos">
            &larr; New Sale / POS
        </a>
    </div>

    <!-- Thermal Paper Receipt Container -->
    <main class="thermal-slip" aria-label="Official Receipt">
        <header class="slip-header">
            <h1 class="slip-brand">ScreenBites Cinema</h1>
            <p class="slip-subtitle">Cinema Reservation &amp; Concessions POS</p>
            <p class="slip-tin">VAT REG TIN: 009-876-543-000<br>Mall of Asia Complex, Pasay City</p>
        </header>

        <div class="divider"></div>

        <table class="meta-table">
            <tr>
                <td>RECEIPT #:</td>
                <td><?= $e($order['order_number'] ?? 'N/A'); ?></td>
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
                <td><?= $e($order['order_status'] ?? 'COMPLETED'); ?></td>
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
        <span class="section-label">Payment Breakdown (FR9)</span>

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

</body>
</html>
