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

// Include Authentication Helpers (PedalWorks-Dynamics pattern)
require_once __DIR__ . '/auth.php';

// Simple helper functions
function format_currency($amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

function sanitize($data): string {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void {
    if ($type === 'success') {
        $_SESSION['flash_success'] = $message;
    } elseif ($type === 'error') {
        $_SESSION['flash_error'] = $message;
    } else {
        $_SESSION['flash_warning'] = $message;
    }
}

function get_youtube_id(?string $urlOrId): ?string {
    if (empty($urlOrId)) return null;
    $trimmed = trim($urlOrId);
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $trimmed)) {
        return $trimmed;
    }
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $trimmed, $match)) {
        return $match[1];
    }
    return $trimmed;
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
