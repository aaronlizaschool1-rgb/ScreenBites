<?php
/**
 * ScreenBites: Audit Trail Logger Engine
 * 
 * Provides centralized static logging methods to fulfill FR16
 * (Transaction Audit Logs) across all transaction and inventory modules.
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace Core;

use App\Models\AuditLog;
use Throwable;

class Logger
{
    /**
     * Write an operational event to the audit trail
     *
     * @param string $actionType Must match ENUM: 'CANCEL_TICKET', 'REFUND_ORDER', 'INVENTORY_OVERRIDE', 'EXPENSE_MODIFIED', 'LOGIN_FAILURE'
     * @param string $targetTable Name of the modified database table
     * @param int $recordId Primary key ID of the affected record
     * @param string|array<string, mixed> $details Contextual summary or state changes
     * @param int|null $userId Optional actor ID (defaults to current logged-in user)
     * @return bool True if logged successfully
     */
    public static function log(
        string $actionType,
        string $targetTable,
        int $recordId,
        string|array $details,
        ?int $userId = null
    ): bool {
        try {
            $actorId = $userId ?? Auth::id();

            // Default to system admin ID (1) if action happened outside active session (e.g. unknown login failure)
            if ($actorId === null) {
                $actorId = 1;
            }

            // Convert array payloads to JSON string
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
            // Fail silently to avoid interrupting the main business transaction, but write to Apache error log
            error_log("[Audit Log Failure] Failed to record {$actionType} on {$targetTable} ID {$recordId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve remote client IP address safely
     */
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