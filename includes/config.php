<?php
/**
 * ScreenBites - Database Configuration & Global Setup
 * Patterned after itim211-2b-shop
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host     = "localhost";
$db_username = "root";
$db_passwd   = "";
$db_name     = "screenbites_db";

$conn = mysqli_connect($db_host, $db_username, $db_passwd, $db_name) 
    or die("Could not connect to database!\n" . mysqli_connect_error());

mysqli_set_charset($conn, "utf8mb4");

// Currency & Business constants
define('CURRENCY_SYMBOL', '₱');
define('TAX_RATE', 0.12);

// Dynamic Base URL computation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs');
$relativeSubdir = str_replace($docRoot, '', $projectRoot);
define('BASE_URL', rtrim($protocol . $host . $relativeSubdir, '/'));

// Simple helper functions
function format_currency($amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

function sanitize($data): string {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool {
    return !empty($_SESSION['user']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool {
    return is_logged_in() && (($_SESSION['user']['role'] ?? '') === 'Administrator');
}

function require_login(): void {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

function audit_log(string $actionType, string $targetTable, int $recordId, string $details): void {
    global $conn;
    if (!$conn) { return; }

    $userId = (int)(current_user()['user_id'] ?? 1);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $actionEsc = mysqli_real_escape_string($conn, $actionType);
    $tableEsc  = mysqli_real_escape_string($conn, $targetTable);
    $detailEsc = mysqli_real_escape_string($conn, $details);
    $ipEsc     = mysqli_real_escape_string($conn, $ip);

    $sql = "INSERT INTO audit_logs (user_id, action_type, target_table, record_id, details, ip_address, created_at)
            VALUES ({$userId}, '{$actionEsc}', '{$tableEsc}', {$recordId}, '{$detailEsc}', '{$ipEsc}', NOW())";
    mysqli_query($conn, $sql);
}
