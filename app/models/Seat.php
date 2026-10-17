<?php
/**
 * ScreenBites: Seat Model
 * 
 * Handles seat matrix coordinates, tier associations, and seat status queries (FR2, FR3).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class Seat
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a specific seat by ID
     * 
     * @param int $seatId
     * @return array<string, mixed>|null
     */
    public function findById(int $seatId): ?array
    {
        $sql = "SELECT s.seat_id, s.hall_id, s.tier_id, s.seat_row, s.seat_number, s.is_active,
                       t.tier_name, t.tier_surcharge
                FROM seats s
                INNER JOIN seat_tiers t ON s.tier_id = t.tier_id
                WHERE s.seat_id = :seat_id 
                LIMIT 1";

        return $this->db->fetchOne($sql, [':seat_id' => $seatId]);
    }

    /**
     * Retrieve complete seat grid with live status for a specific screening
     * Returns categorized state: 'Sold', 'Held', 'HeldByMe', or 'Available'
     * 
     * @param int $screeningId
     * @param int $hallId
     * @param string $sessionToken Current user's PHP session ID
     * @param float $basePrice Base ticket price for this screening
     * @return array<int, array<string, mixed>>
     */
    public function getScreeningSeatMatrix(
        int $screeningId,
        int $hallId,
        string $sessionToken,
        float $basePrice
    ): array {
        // Clean expired holds before querying
        $cleanHoldSql = "DELETE FROM seat_holds WHERE hold_expires_at <= NOW()";
        $this->db->execute($cleanHoldSql);

        $sql = "SELECT 
                    s.seat_id,
                    s.hall_id,
                    s.seat_row,
                    s.seat_number,
                    s.tier_id,
                    st.tier_name,
                    st.tier_surcharge,
                    ROUND((:base_price + st.tier_surcharge), 2) AS calculated_price,
                    CASE
                        -- 1. Sold Ticket Check
                        WHEN tk.ticket_id IS NOT NULL THEN 'Sold'
                        -- 2. Active Temporary Hold Check
                        WHEN sh.screening_id IS NOT NULL AND sh.session_token = :session_token THEN 'HeldByMe'
                        WHEN sh.screening_id IS NOT NULL THEN 'Held'
                        -- 3. Available
                        ELSE 'Available'
                    END AS seat_status,
                    sh.hold_expires_at,
                    TIMESTAMPDIFF(SECOND, NOW(), sh.hold_expires_at) AS seconds_remaining
                FROM seats s
                INNER JOIN seat_tiers st ON s.tier_id = st.tier_id
                -- Join valid tickets
                LEFT JOIN tickets tk ON s.seat_id = tk.seat_id 
                    AND tk.screening_id = :screening_id 
                    AND tk.ticket_status = 'Valid'
                -- Join active holds
                LEFT JOIN seat_holds sh ON s.seat_id = sh.seat_id 
                    AND sh.screening_id = :screening_id2 
                    AND sh.hold_expires_at > NOW()
                WHERE s.hall_id = :hall_id AND s.is_active = 1
                ORDER BY s.seat_row ASC, s.seat_number ASC";

        return $this->db->fetchAll($sql, [
            ':base_price'    => $basePrice,
            ':session_token' => $sessionToken,
            ':screening_id'  => $screeningId,
            ':screening_id2' => $screeningId,
            ':hall_id'       => $hallId
        ]);
    }
}