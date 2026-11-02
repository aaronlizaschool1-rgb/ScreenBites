<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once dirname(__DIR__) . '/PHPMailer/src/Exception.php';
require_once dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/fpdf/fpdf.php';
require_once __DIR__ . '/config.php';

function fetch_order_receipt_data(mysqli $conn, int $orderId): ?array {
    $stmt = mysqli_prepare($conn, "
        SELECT
            o.order_id,
            o.order_number,
            o.customer_name,
            o.customer_email,
            o.subtotal_amount,
            o.tax_amount,
            (o.subtotal_amount + o.tax_amount) AS grand_total,
            o.order_status,
            o.created_at AS order_date,
            u.full_name AS cashier_name,
            u.username AS cashier_username,
            p.payment_method,
            p.amount_paid,
            p.change_returned,
            p.payment_date
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id
        LEFT JOIN payments p ON o.order_id = p.order_id
        WHERE o.order_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$order) {
        return null;
    }

    $tickets = [];
    $tStmt = mysqli_prepare($conn, "
        SELECT
            t.ticket_id,
            t.price_charged,
            t.ticket_status,
            m.title AS movie_title,
            m.rating AS movie_rating,
            h.hall_name,
            s.seat_row,
            s.seat_number,
            st.tier_name,
            sc.screening_date,
            sc.start_time
        FROM tickets t
        INNER JOIN screenings sc ON t.screening_id = sc.screening_id
        INNER JOIN movies m ON sc.movie_id = m.movie_id
        INNER JOIN halls h ON t.hall_id = h.hall_id
        INNER JOIN seats s ON t.seat_id = s.seat_id
        INNER JOIN seat_tiers st ON s.tier_id = st.tier_id
        WHERE t.order_id = ?
        ORDER BY s.seat_row ASC, s.seat_number ASC
    ");
    if ($tStmt) {
        mysqli_stmt_bind_param($tStmt, "i", $orderId);
        mysqli_stmt_execute($tStmt);
        $tRes = mysqli_stmt_get_result($tStmt);
        while ($tRow = mysqli_fetch_assoc($tRes)) {
            $tickets[] = $tRow;
        }
        mysqli_stmt_close($tStmt);
    }

    $concessions = [];
    $cStmt = mysqli_prepare($conn, "
        SELECT
            oi.order_item_id,
            p.product_name,
            p.category,
            oi.quantity,
            oi.unit_price_charged,
            (oi.quantity * oi.unit_price_charged) AS line_total
        FROM order_items oi
        INNER JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    if ($cStmt) {
        mysqli_stmt_bind_param($cStmt, "i", $orderId);
        mysqli_stmt_execute($cStmt);
        $cRes = mysqli_stmt_get_result($cStmt);
        while ($cRow = mysqli_fetch_assoc($cRes)) {
            $concessions[] = $cRow;
        }
        mysqli_stmt_close($cStmt);
    }

    $combos = [];
    $cbStmt = mysqli_prepare($conn, "
        SELECT
            oc.order_combo_id,
            cb.combo_name,
            oc.quantity,
            oc.unit_price_charged,
            (oc.quantity * oc.unit_price_charged) AS line_total
        FROM order_combos oc
        INNER JOIN combos cb ON oc.combo_id = cb.combo_id
        WHERE oc.order_id = ?
    ");
    if ($cbStmt) {
        mysqli_stmt_bind_param($cbStmt, "i", $orderId);
        mysqli_stmt_execute($cbStmt);
        $cbRes = mysqli_stmt_get_result($cbStmt);
        while ($cbRow = mysqli_fetch_assoc($cbRes)) {
            $combos[] = $cbRow;
        }
        mysqli_stmt_close($cbStmt);
    }

    return [
        'order'       => $order,
        'tickets'     => $tickets,
        'concessions' => $concessions,
        'combos'      => $combos
    ];
}

function generate_receipt_pdf(int $orderId, array $orderData = [], string $outputMode = 'I'): string {
    global $conn;

    if (empty($orderData)) {
        $orderData = fetch_order_receipt_data($conn, $orderId);
    }

    if (!$orderData || empty($orderData['order'])) {
        if ($outputMode === 'S') return '';
        die("Order #{$orderId} not found for receipt generation.");
    }

    $ord = $orderData['order'];
    $tickets = $orderData['tickets'] ?? [];
    $concessions = $orderData['concessions'] ?? [];
    $combos = $orderData['combos'] ?? [];

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    $pdf->SetFillColor(30, 41, 59);
    $pdf->Rect(0, 0, 210, 32, 'F');

    $pdf->SetTextColor(245, 158, 11);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetXY(15, 8);
    $pdf->Cell(0, 8, 'SCREENBITES CINEMA & POS LOUNGE', 0, 1, 'L');

    $pdf->SetTextColor(226, 232, 240);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetX(15);
    $pdf->Cell(0, 6, 'Official Sales & Booking Transaction Receipt | Philippines (VAT Registered)', 0, 1, 'L');

    $pdf->Ln(8);
    $pdf->SetTextColor(30, 41, 59);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(100, 6, 'Order Reference: #' . $ord['order_number'], 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(80, 6, 'Date: ' . date('F j, Y, g:i A', strtotime($ord['order_date'])), 0, 1, 'R');

    $custName = !empty($ord['customer_name']) ? $ord['customer_name'] : 'Walk-in Cinema Guest';
    $custEmail = !empty($ord['customer_email']) ? $ord['customer_email'] : 'N/A';
    $cashier = !empty($ord['cashier_name']) ? $ord['cashier_name'] : 'Self-Service POS';

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(100, 6, 'Customer: ' . $custName . ' (' . $custEmail . ')', 0, 0, 'L');
    $pdf->Cell(80, 6, 'Cashier / Staff: ' . $cashier, 0, 1, 'R');

    $pdf->Cell(100, 6, 'Status: ' . $ord['order_status'], 0, 0, 'L');
    $pdf->Cell(80, 6, 'Payment Method: ' . ($ord['payment_method'] ?? 'Cash'), 0, 1, 'R');

    $pdf->Ln(4);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(4);

    $pdf->SetFillColor(241, 245, 249);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(95, 7, '  Item Description & Details', 1, 0, 'L', true);
    $pdf->Cell(25, 7, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(30, 7, 'Total (PHP)', 1, 1, 'R', true);

    $pdf->SetFont('Arial', '', 9);

    if (!empty($tickets)) {
        foreach ($tickets as $t) {
            $seatLabel = 'Seat ' . $t['seat_row'] . $t['seat_number'] . ' (' . $t['tier_name'] . ')';
            $showtime = date('M d, g:i A', strtotime($t['screening_date'] . ' ' . $t['start_time']));
            $desc = $t['movie_title'] . ' [' . $seatLabel . ' | ' . $t['hall_name'] . ' - ' . $showtime . ']';

            $pdf->Cell(95, 7, ' ' . substr($desc, 0, 52), 1, 0, 'L');
            $pdf->Cell(25, 7, '1 seat', 1, 0, 'C');
            $pdf->Cell(30, 7, number_format((float)$t['price_charged'], 2), 1, 0, 'R');
            $pdf->Cell(30, 7, number_format((float)$t['price_charged'], 2), 1, 1, 'R');
        }
    }

    if (!empty($concessions)) {
        foreach ($concessions as $c) {
            $desc = $c['product_name'] . ' (' . $c['category'] . ')';
            $pdf->Cell(95, 7, ' ' . substr($desc, 0, 52), 1, 0, 'L');
            $pdf->Cell(25, 7, (string)$c['quantity'], 1, 0, 'C');
            $pdf->Cell(30, 7, number_format((float)$c['unit_price_charged'], 2), 1, 0, 'R');
            $pdf->Cell(30, 7, number_format((float)$c['line_total'], 2), 1, 1, 'R');
        }
    }

    if (!empty($combos)) {
        foreach ($combos as $cb) {
            $desc = $cb['combo_name'] . ' [Combo Bundle]';
            $pdf->Cell(95, 7, ' ' . substr($desc, 0, 52), 1, 0, 'L');
            $pdf->Cell(25, 7, (string)$cb['quantity'], 1, 0, 'C');
            $pdf->Cell(30, 7, number_format((float)$cb['unit_price_charged'], 2), 1, 0, 'R');
            $pdf->Cell(30, 7, number_format((float)$cb['line_total'], 2), 1, 1, 'R');
        }
    }

    $pdf->Ln(2);

    $subtotal = (float)$ord['subtotal_amount'];
    $vat = (float)$ord['tax_amount'];
    $grand = (float)$ord['grand_total'];
    $paid = (float)($ord['amount_paid'] ?? $grand);
    $change = (float)($ord['change_returned'] ?? 0.00);

    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(120, 6, '', 0, 0);
    $pdf->Cell(30, 6, 'Vatable Subtotal:', 0, 0, 'R');
    $pdf->Cell(30, 6, 'PHP ' . number_format($subtotal, 2), 0, 1, 'R');

    $pdf->Cell(120, 6, '', 0, 0);
    $pdf->Cell(30, 6, 'VAT (12% Included):', 0, 0, 'R');
    $pdf->Cell(30, 6, 'PHP ' . number_format($vat, 2), 0, 1, 'R');

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(254, 243, 199);
    $pdf->Cell(120, 8, '', 0, 0);
    $pdf->Cell(30, 8, 'Grand Total:', 1, 0, 'R', true);
    $pdf->Cell(30, 8, 'PHP ' . number_format($grand, 2), 1, 1, 'R', true);

    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(120, 6, '', 0, 0);
    $pdf->Cell(30, 6, 'Tender (' . ($ord['payment_method'] ?? 'Cash') . '):', 0, 0, 'R');
    $pdf->Cell(30, 6, 'PHP ' . number_format($paid, 2), 0, 1, 'R');

    $pdf->Cell(120, 6, '', 0, 0);
    $pdf->Cell(30, 6, 'Change Returned:', 0, 0, 'R');
    $pdf->Cell(30, 6, 'PHP ' . number_format($change, 2), 0, 1, 'R');

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->MultiCell(0, 4, "Thank you for watching with ScreenBites Cinema Lounge!\nPlease present this digital PDF receipt at the cinema auditorium doors for barcode seat admission.\nFor customer inquiries or refunds, please quote Reference #" . $ord['order_number'] . ".");

    $pdfFileName = 'ScreenBites_Receipt_' . $ord['order_number'] . '.pdf';

    if ($outputMode === 'S') {
        return $pdf->Output('S');
    }

    $pdf->Output($outputMode, $pdfFileName);
    return '';
}

function send_transaction_email(int $orderId, string $recipientEmail, array $orderData = [], string $statusNote = ''): bool {
    global $conn;

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (empty($orderData)) {
        $orderData = fetch_order_receipt_data($conn, $orderId);
    }

    if (!$orderData || empty($orderData['order'])) {
        return false;
    }

    $ord = $orderData['order'];
    $tickets = $orderData['tickets'] ?? [];
    $concessions = $orderData['concessions'] ?? [];
    $combos = $orderData['combos'] ?? [];

    $pdfBinary = generate_receipt_pdf($orderId, $orderData, 'S');

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = defined('MAILTRAP_HOST') ? MAILTRAP_HOST : 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Port       = defined('MAILTRAP_PORT') ? MAILTRAP_PORT : 2525;
        $mail->Username   = defined('MAILTRAP_USER') ? MAILTRAP_USER : '690927f441cfe6';
        $mail->Password   = defined('MAILTRAP_PASS') ? MAILTRAP_PASS : '81ecd079d6b1a2';
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPSecure = 'tls';

        $mail->SMTPOptions = [
    'ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ],
];

        $mail->setFrom('orders@screenbites.com', 'ScreenBites Cinema Lounge');
        $customerDisplayName = !empty($ord['customer_name']) ? $ord['customer_name'] : 'Cinema Guest';
        $mail->addAddress($recipientEmail, $customerDisplayName);

        if (!empty($pdfBinary)) {
            $attachmentName = 'ScreenBites_Receipt_' . $ord['order_number'] . '.pdf';
            $mail->addStringAttachment($pdfBinary, $attachmentName, 'base64', 'application/pdf');
        }

        $itemsHtml = '';
        foreach ($tickets as $t) {
            $seatInfo = 'Seat ' . $t['seat_row'] . $t['seat_number'] . ' (' . $t['tier_name'] . ')';
            $showtime = date('M d, g:i A', strtotime($t['screening_date'] . ' ' . $t['start_time']));
            $desc = htmlspecialchars($t['movie_title']) . '<br><small style="color: #64748b;">' . $seatInfo . ' | ' . htmlspecialchars($t['hall_name']) . ' - ' . $showtime . '</small>';
            $itemsHtml .= "<tr>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0;'>{$desc}</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: center;'>1</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: right;'>PHP " . number_format((float)$t['price_charged'], 2) . "</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: right;'>PHP " . number_format((float)$t['price_charged'], 2) . "</td>
            </tr>";
        }
        foreach ($concessions as $c) {
            $desc = htmlspecialchars($c['product_name']) . ' (' . htmlspecialchars($c['category']) . ')';
            $itemsHtml .= "<tr>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0;'>{$desc}</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: center;'>{$c['quantity']}</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: right;'>PHP " . number_format((float)$c['unit_price_charged'], 2) . "</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: right;'>PHP " . number_format((float)$c['line_total'], 2) . "</td>
            </tr>";
        }
        foreach ($combos as $cb) {
            $desc = htmlspecialchars($cb['combo_name']) . ' [Bundle]';
            $itemsHtml .= "<tr>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0;'>{$desc}</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: center;'>{$cb['quantity']}</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: right;'>PHP " . number_format((float)$cb['unit_price_charged'], 2) . "</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: right;'>PHP " . number_format((float)$cb['line_total'], 2) . "</td>
            </tr>";
        }

        $mail->isHTML(true);
        $orderStatusDisplay = htmlspecialchars($ord['order_status']);
        $mail->Subject = "ScreenBites Cinema: Order #{$ord['order_number']} Status: {$orderStatusDisplay}";

        $statusBannerHtml = '';
        if (!empty($statusNote)) {
            $statusBannerHtml = "<div style='background: #eef2ff; border-left: 4px solid #6366f1; padding: 12px 16px; margin: 16px 0; font-size: 13px; color: #312e81; border-radius: 4px;'>
                <strong>Status Update:</strong> " . htmlspecialchars($statusNote) . "
            </div>";
        }

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
            <div style='background: #1e293b; padding: 20px; text-align: center;'>
                <h1 style='color: #f59e0b; margin: 0; font-size: 22px;'>ScreenBites Cinema &amp; POS Lounge</h1>
                <p style='color: #94a3b8; margin: 5px 0 0; font-size: 13px;'>Official Order Status Notification &amp; Digital Receipt</p>
            </div>
            <div style='padding: 24px;'>
                <p style='font-size: 15px; color: #1e293b; margin-top: 0;'>Hello <strong>" . htmlspecialchars($customerDisplayName) . "</strong>,</p>
                <p style='color: #475569; font-size: 14px;'>Here is the current operational status of your ScreenBites order. Your official digital receipt is attached as a PDF.</p>

                {$statusBannerHtml}

                <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin: 16px 0;'>
                    <div style='font-size: 13px; color: #64748b;'>Order Reference: <strong style='color: #1e293b; font-size: 14px; font-family: monospace;'>#" . htmlspecialchars($ord['order_number']) . "</strong></div>
                    <div style='font-size: 13px; color: #64748b; margin-top: 4px;'>Order Status: <strong style='color: #047857; font-size: 13px; text-transform: uppercase;'>{$orderStatusDisplay}</strong></div>
                    <div style='font-size: 13px; color: #64748b; margin-top: 4px;'>Transaction Date: " . date('F j, Y, g:i A', strtotime($ord['order_date'])) . "</div>
                    <div style='font-size: 13px; color: #64748b; margin-top: 4px;'>Payment Method: " . htmlspecialchars($ord['payment_method'] ?? 'Cash') . "</div>
                </div>

                <table style='width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px;'>
                    <thead>
                        <tr style='background: #f1f5f9; color: #334155; text-align: left;'>
                            <th style='padding: 8px 12px; border-bottom: 2px solid #cbd5e1;'>Item Description</th>
                            <th style='padding: 8px 12px; border-bottom: 2px solid #cbd5e1; text-align: center;'>Qty</th>
                            <th style='padding: 8px 12px; border-bottom: 2px solid #cbd5e1; text-align: right;'>Unit Price</th>
                            <th style='padding: 8px 12px; border-bottom: 2px solid #cbd5e1; text-align: right;'>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' style='padding: 6px 12px; text-align: right; color: #64748b;'>Vatable Subtotal:</td>
                            <td style='padding: 6px 12px; text-align: right; color: #1e293b;'>PHP " . number_format((float)$ord['subtotal_amount'], 2) . "</td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding: 6px 12px; text-align: right; color: #64748b;'>VAT (12% Included):</td>
                            <td style='padding: 6px 12px; text-align: right; color: #1e293b;'>PHP " . number_format((float)$ord['tax_amount'], 2) . "</td>
                        </tr>
                        <tr style='background: #fef3c7; font-weight: bold;'>
                            <td colspan='3' style='padding: 10px 12px; text-align: right; color: #92400e; font-size: 14px;'>Grand Total:</td>
                            <td style='padding: 10px 12px; text-align: right; color: #92400e; font-size: 14px;'>PHP " . number_format((float)$ord['grand_total'], 2) . "</td>
                        </tr>
                    </tfoot>
                </table>

                <div style='margin-top: 24px; padding-top: 16px; border-top: 1px dashed #cbd5e1; font-size: 12px; color: #64748b; text-align: center;'>
                    Please present your attached PDF receipt at the cinema auditorium doors for entry.<br>
                    ScreenBites Cinema Lounge &bull; Metro Manila, Philippines
                </div>
            </div>
        </div>";

        $mail->send();

        if (function_exists('audit_log')) {
            audit_log('EMAIL_RECEIPT_SENT', 'orders', (int)$orderId, "Dispatched digital receipt for Order #{$ord['order_number']} to {$recipientEmail}");
        }

        return true;
    } catch (\Throwable $e) {
        if (function_exists('audit_log')) {
            audit_log('EMAIL_RECEIPT_FAILED', 'orders', (int)$orderId, "Failed to send email to {$recipientEmail}: " . $e->getMessage());
        }
        return false;
    }
}
