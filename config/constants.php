<?php

declare(strict_types=1);

define('APP_NAME', 'ScreenBites');
define('APP_TAGLINE', 'Cinema Reservation & Snackbar System');
define('APP_VERSION', '1.0.0');

define('SEAT_HOLD_DURATION_SECONDS', 600);
define('SEAT_HOLD_INTERVAL_SQL', '10 MINUTE');

define('ROLE_ADMIN', 'Administrator');
define('ROLE_TICKETING', 'Ticketing Staff');
define('ROLE_CASHIER', 'Snackbar Cashier');

define('SYSTEM_ROLES', [
    ROLE_ADMIN,
    ROLE_TICKETING,
    ROLE_CASHIER
]);

define('TAX_RATE', 0.12);
define('CURRENCY_SYMBOL', '₱');
define('CURRENCY_CODE', 'PHP');

define('PAYMENT_CASH', 'Cash');
define('PAYMENT_EWALLET', 'E-Wallet');
define('PAYMENT_CARD', 'Card');
define('PAYMENT_METHODS', [
    PAYMENT_CASH,
    PAYMENT_EWALLET,
    PAYMENT_CARD
]);

define('STATUS_COMPLETED', 'Completed');
define('STATUS_CANCELLED', 'Cancelled');
define('STATUS_REFUNDED', 'Refunded');
define('TICKET_VALID', 'Valid');
define('TICKET_CANCELLED', 'Cancelled');

define('DEFAULT_REORDER_LEVEL', 15);

define('UTILITY_ELECTRICITY', 'Electricity');
define('UTILITY_WATER', 'Water');
define('UTILITY_TYPES', [
    UTILITY_ELECTRICITY,
    UTILITY_WATER
]);

define('AUDIT_CANCEL_TICKET', 'CANCEL_TICKET');
define('AUDIT_REFUND_ORDER', 'REFUND_ORDER');
define('AUDIT_INVENTORY_OVERRIDE', 'INVENTORY_OVERRIDE');
define('AUDIT_EXPENSE_MODIFIED', 'EXPENSE_MODIFIED');
define('AUDIT_LOGIN_FAILURE', 'LOGIN_FAILURE');
