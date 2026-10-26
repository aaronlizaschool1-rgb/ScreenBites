<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    $secretKey =$_GET['key'] ?? '';
    if ($secretKey !== 'screenbites_cron_secret_2026') {
        http_response_code(403);
        echo "Access denied: CLI execution or authorized cron key required.\n";
        exit;
    }
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/app/models/SeatHold.php';

use App\Models\SeatHold;

try {
    $seatHoldModel = new SeatHold();
    $releasedCount =$seatHoldModel->purgeExpiredHolds();

    $timestamp = date('Y-m-d H:i:s');$logMessage = "[{$timestamp}] [Seat Cleanup] Purged {$releasedCount} expired seat hold(s).\n";

    echo $logMessage;

    $logDir = ROOT_PATH . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/cron.log',$logMessage, FILE_APPEND);
} catch (\Throwable $e) {
    echo "[Error] Failed to release expired seats: " . $e->getMessage() . "\n";
    exit(1);
}
