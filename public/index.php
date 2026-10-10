<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

try {
    $db = Database::getInstance();
    $userCount = $db->fetchColumn("SELECT COUNT(*) FROM users");
    echo "Connected successfully to ScreenBites DB! Users found: " . $userCount;
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}