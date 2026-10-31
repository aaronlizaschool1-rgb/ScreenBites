<?php
/**
 * ScreenBites - Procedural Authentication & Role Authorization
 * Modeled after PedalWorks-Dynamics/includes/admin_auth.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool {
    return !empty($_SESSION['user']) && is_array($_SESSION['user']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function has_role($allowedRoles): bool {
    if (!is_logged_in()) {
        return false;
    }
    $currentRole = $_SESSION['user']['role'] ?? '';
    if ($currentRole === 'Administrator') {
        return true;
    }
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    return in_array($currentRole, $allowedRoles, true);
}

function is_admin(): bool {
    return has_role(['Administrator']);
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['flash_warning'] = 'Please sign in to access that section.';
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        $_SESSION['flash_error'] = 'Access restricted: Administrator credentials required.';
        header("Location: " . (is_logged_in() ? BASE_URL . "/index.php" : BASE_URL . "/login.php"));
        exit;
    }
}

function require_role($allowedRoles): void {
    if (!has_role($allowedRoles)) {
        $roleName = $_SESSION['user']['role'] ?? 'Your role';
        $_SESSION['flash_error'] = "Access denied: [{$roleName}] does not have permission to access that module.";
        header("Location: " . (is_logged_in() ? BASE_URL . "/index.php" : BASE_URL . "/login.php"));
        exit;
    }
}
