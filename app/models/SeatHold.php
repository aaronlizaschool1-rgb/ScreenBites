<?php
/**
 * ScreenBites: Seat Hold Model
 * 
 * Manages atomic seat lock acquisitions and timeout sweeps for FR4.
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use PDOException;

class SeatHold
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Purge all expired locks from the database
     * 
     * @return int Count of purged expired holds
     */
    public function purgeExpiredHolds(): int
    {
        $sql = "DELETE FROM seat_holds WHERE hold_expires_at <= NOW()";
        return $this->db->execute($sql);
    }

    /**
     * Attempt to atomically lock a seat for a user session
     * 
     * @param int $screeningId
     * @param int $seatId
     * @param int $hallId
     * @param string $sessionToken
     * @param int $durationSeconds Default 600 seconds (10 minutes)
     * @return array{success: bool, message: string, expires_at: ?string}
     */
    public function acquireHold(
        int $screeningId,
        int $seatId,
        int $hallId,
        string $sessionToken,
        int $durationSeconds = 600
    ): array {
        // Step 1: Sweep expired records to free up stale holds
        $this->purgeExpiredHolds();

        // Step 2: Validate the seat is not already sold
        $ticketCheckSql = "SELECT COUNT(*) FROM tickets 
                           WHERE screening_id = :screening_id 
                             AND seat_id = :seat_id 
                             AND ticket_status = 'Valid'";
        
        $isSold = ((int)$this->db->fetchColumn($ticketCheckSql, [
            ':screening_id' => $screeningId,
            ':seat_id'      => $seatId
        ])) > 0;

        if ($isSold) {
            return [
                'success'    => false,
                'message'    => 'This seat has already been sold.',
                'expires_at' => null
            ];
        }

        // Step 3: Insert or update hold atomically using transaction
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $durationSeconds);

            // Utilizing ON DUPLICATE KEY UPDATE in case current session extends hold
            $insertHoldSql = "INSERT INTO seat_holds (screening_id, seat_id, hall_id, session_token, hold_expires_at, created_at)
                              VALUES (:screening_id, :seat_id, :hall_id, :session_token, :expires_at, NOW())
                              ON DUPLICATE KEY UPDATE
                                  hold_expires_at = IF(session_token = VALUES(session_token), VALUES(hold_expires_at), hold_expires_at)";

            $this->db->query($insertHoldSql, [
                ':screening_id'   => $screeningId,
                ':seat_id'        => $seatId,
                ':hall_id'        => $hallId,
                ':session_token'  => $sessionToken,
                ':expires_at'     => $expiresAt
            ]);

            // Verify if lock actually belongs to this session
            $verifySql = "SELECT session_token FROM seat_holds 
                          WHERE screening_id = :screening_id AND seat_id = :seat_id";
            
            $activeToken = $this->db->fetchColumn($verifySql, [
                ':screening_id' => $screeningId,
                ':seat_id'      => $seatId
            ]);

            if ($activeToken === $sessionToken) {
                return [
                    'success'    => true,
                    'message'    => 'Seat held successfully.',
                    'expires_at' => $expiresAt
                ];
            }

            return [
                'success'    => false,
                'message'    => 'Seat is currently locked by another customer.',
                'expires_at' => null
            ];
        } catch (PDOException $e) {
            return [
                'success'    => false,
                'message'    => 'Seat lock conflict: ' . $e->getMessage(),
                'expires_at' => null
            ];
        }
    }

    /**
     * Release an explicitly unselected seat held by the current session
     * 
     * @param int $screeningId
     * @param int $seatId
     * @param string $sessionToken
     * @return bool
     */
    public function releaseHold(int $screeningId, int $seatId, string $sessionToken): bool
    {
        $sql = "DELETE FROM seat_holds 
                WHERE screening_id = :screening_id 
                  AND seat_id = :seat_id 
                  AND session_token = :session_token";

        return $this->db->execute($sql, [
            ':screening_id'  => $screeningId,
            ':seat_id'       => $seatId,
            ':session_token' => $sessionToken
        ]) > 0;
    }

    /**
     * Retrieve all active seat IDs held by a given session for a screening
     * 
     * @param int $screeningId
     * @param string $sessionToken
     * @return array<int, int>
     */
    public function getSessionHeldSeatIds(int $screeningId, string $sessionToken): array
    {
        $sql = "SELECT seat_id FROM seat_holds 
                WHERE screening_id = :screening_id 
                  AND session_token = :session_token 
                  AND hold_expires_at > NOW()";

        $rows = $this->db->fetchAll($sql, [
            ':screening_id'  => $screeningId,
            ':session_token' => $sessionToken
        ]);

        return array_map(fn($row) => (int)$row['seat_id'], $rows);
    }
}