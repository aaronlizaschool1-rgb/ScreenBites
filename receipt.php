<?php
/**
 * ScreenBites - Procedural PDF Receipt Streamer
 * Term Test Lab: Official Digital PDF Receipt Generator
 * Patterned after PedalWorks-Dynamics procedural receipt streamer
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    http_response_code(400);
    die("Invalid order reference specified for receipt generation.");
}

// Generate and stream PDF to browser inline (mode 'I')
generate_receipt_pdf($orderId, [], 'I');
exit;
