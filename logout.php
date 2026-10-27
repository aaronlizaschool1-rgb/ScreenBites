<?php
/**
 * ScreenBites - Staff Logout
 * Modeled after itim211-2b-shop
 */

require_once __DIR__ . '/includes/config.php';

unset($_SESSION['user']);
session_destroy();

header("Location: " . BASE_URL . "/login.php");
exit;
