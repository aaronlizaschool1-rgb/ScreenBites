<?php
/**
 * ScreenBites - Global Application Constants & Business Rules
 * Aligned strictly with Functional Requirements (FR1 - FR16)
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

// Application Identity
define('APP_NAME', 'ScreenBites');
define('APP_TAGLINE', 'Cinema Reservation & Snackbar System');
define('APP_VERSION', '1.0.0');

// FR4: Seat Hold & Timeout Settings
// Defines the temporary reservation hold window before automatic release (in seconds)
define('SEAT_HOLD_DURATION_SECONDS', 600); // 10 Minutes (600s)
define('SEAT_HOLD_INTERVAL_SQL', '10 MINUTE');

// FR13: Role-Based Access Control (RBAC) Constants
define('ROLE_ADMIN', 'Administrator');
define('ROLE_TICKETING', 'Ticketing Staff');
define('ROLE_CASHIER', 'Snackbar Cashier');

// List of valid roles for validation checks
define('SYSTEM_ROLES', [
    ROLE_ADMIN,
    ROLE_TICKETING,
    ROLE_CASHIER
]);

// FR9 & FR10: Financial & Taxation Constants
define('TAX_RATE', 0.12); // 12% Value Added Tax (VAT)
define('CURRENCY_SYMBOL', '₱');
define('CURRENCY_CODE', 'PHP');

// Payment Methods Supported
define('PAYMENT_CASH', 'Cash');
define('PAYMENT_EWALLET', 'E-Wallet');
define('PAYMENT_CARD', 'Card');
define('PAYMENT_METHODS', [
    PAYMENT_CASH,
    PAYMENT_EWALLET,
    PAYMENT_CARD
]);

// Order & Ticket Statuses
define('STATUS_COMPLETED', 'Completed');
define('STATUS_CANCELLED', 'Cancelled');
define('STATUS_REFUNDED', 'Refunded');
define('TICKET_VALID', 'Valid');
define('TICKET_CANCELLED', 'Cancelled');

// FR7: Concessions Default Inventory Thresholds
define('DEFAULT_REORDER_LEVEL', 15);

// FR11: Utility Expense Classifications
define('UTILITY_ELECTRICITY', 'Electricity');
define('UTILITY_WATER', 'Water');
define('UTILITY_TYPES', [
    UTILITY_ELECTRICITY,
    UTILITY_WATER
]);

// FR16: Transaction Audit Action Identifiers
define('AUDIT_CANCEL_TICKET', 'CANCEL_TICKET');
define('AUDIT_REFUND_ORDER', 'REFUND_ORDER');
define('AUDIT_INVENTORY_OVERRIDE', 'INVENTORY_OVERRIDE');
define('AUDIT_EXPENSE_MODIFIED', 'EXPENSE_MODIFIED');
define('AUDIT_LOGIN_FAILURE', 'LOGIN_FAILURE');