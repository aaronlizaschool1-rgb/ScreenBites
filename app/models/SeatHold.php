<?php

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

    public function purgeExpiredHolds(): int
    {
        $sql = "DELETE FROM seat_holds WHERE hold_expires_at <= NOW()";
        return $this->db->execute($sql);
    }

    public function acquireHold(
        int $screeningId,
        int $seatId,
        int $hallId,
        string $sessionToken,
        int $durationSeconds = 600
    ): array {

        $this->purgeExpiredHolds();

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

        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $durationSeconds);

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
