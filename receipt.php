<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    http_response_code(400);
    die("Invalid order reference specified for receipt generation.");
}

generate_receipt_pdf($orderId, [], 'I');
exit;
