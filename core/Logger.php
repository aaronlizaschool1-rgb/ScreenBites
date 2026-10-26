<?php

declare(strict_types=1);

namespace Core;

use App\Models\AuditLog;
use Throwable;

class Logger
{

    public static function log(
        string $actionType,
        string $targetTable,
        int $recordId,
        string|array $details,
        ?int $userId = null
    ): bool {
        try {
            $actorId = $userId ?? Auth::id();

            if ($actorId === null) {
                $actorId = 1;
            }

            $detailsPayload = is_array($details)
                ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string)$details;

            $ipAddress = self::getClientIp();

            $auditModel = new AuditLog();
            return $auditModel->create([
                'user_id'      => $actorId,
                'action_type'  => $actionType,
                'target_table' => $targetTable,
                'record_id'    => $recordId,
                'details'      => $detailsPayload,
                'ip_address'   => $ipAddress
            ]);
        } catch (Throwable $e) {

            error_log("[Audit Log Failure] Failed to record {$actionType} on {$targetTable} ID {$recordId}: " . $e->getMessage());
            return false;
        }
    }

    private static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
