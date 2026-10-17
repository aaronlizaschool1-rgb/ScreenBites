<?php
/**
 * ScreenBites: Screening Model
 * 
 * Handles showtime scheduling, joins with movies and halls,
 * and executes collision-avoidance logic for FR1.
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class Screening
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Retrieve upcoming screenings filtered by optional date
     * 
     * @param string|null $date Format YYYY-MM-DD
     * @return array<int, array<string, mixed>>
     */
    public function getScheduleByDate(?string $date = null): array
    {
        $params = [];
        $whereSql = "";

        if ($date !== null && $date !== '') {
            $whereSql = "WHERE s.screening_date = :screening_date";
            $params[':screening_date'] = $date;
        } else {
            // Default: show today onwards
            $whereSql = "WHERE s.screening_date >= CURDATE()";
        }

        $sql = "SELECT 
                    s.screening_id,
                    s.movie_id,
                    s.hall_id,
                    s.screening_date,
                    s.start_time,
                    s.end_time,
                    s.base_price,
                    m.title AS movie_title,
                    m.duration_minutes,
                    m.rating AS movie_rating,
                    m.poster_path,
                    h.hall_name,
                    (SELECT COUNT(*) FROM tickets t WHERE t.screening_id = s.screening_id AND t.ticket_status = 'Valid') AS sold_seats_count
                FROM screenings s
                INNER JOIN movies m ON s.movie_id = m.movie_id
                INNER JOIN halls h ON s.hall_id = h.hall_id
                {$whereSql}
                ORDER BY s.screening_date ASC, s.start_time ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Find a single screening with all joined movie and hall attributes
     * 
     * @param int $screeningId
     * @return array<string, mixed>|null
     */
    public function findDetailsById(int $screeningId): ?array
    {
        $sql = "SELECT 
                    s.screening_id,
                    s.movie_id,
                    s.hall_id,
                    s.screening_date,
                    s.start_time,
                    s.end_time,
                    s.base_price,
                    m.title AS movie_title,
                    m.duration_minutes,
                    m.rating AS movie_rating,
                    m.poster_path,
                    h.hall_name,
                    h.total_rows,
                    h.total_columns
                FROM screenings s
                INNER JOIN movies m ON s.movie_id = m.movie_id
                INNER JOIN halls h ON s.hall_id = h.hall_id
                WHERE s.screening_id = :screening_id
                LIMIT 1";

        return $this->db->fetchOne($sql, [':screening_id' => $screeningId]);
    }

    /**
     * Check if a proposed screening timeframe overlaps with an existing screening in the same hall.
     * Overlap condition: (StartA < EndB) AND (EndA > StartB)
     * 
     * @param int $hallId
     * @param string $date Format YYYY-MM-DD
     * @param string $startTime Format HH:MM:SS
     * @param string $endTime Format HH:MM:SS
     * @param int|null $excludeScreeningId For updates, pass current screening_id to exclude itself
     * @return bool True if a time collision exists
     */
    public function hasScheduleCollision(
        int $hallId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeScreeningId = null
    ): bool {
        $params = [
            ':hall_id'        => $hallId,
            ':screening_date' => $date,
            ':start_time'     => $startTime,
            ':end_time'       => $endTime
        ];

        $excludeSql = "";
        if ($excludeScreeningId !== null) {
            $excludeSql = "AND screening_id != :exclude_id";
            $params[':exclude_id'] = $excludeScreeningId;
        }

        $sql = "SELECT COUNT(*) FROM screenings 
                WHERE hall_id = :hall_id 
                  AND screening_date = :screening_date 
                  AND (start_time < :end_time AND end_time > :start_time)
                  {$excludeSql}";

        return ((int)$this->db->fetchColumn($sql, $params)) > 0;
    }

    /**
     * Insert a validated screening schedule
     * 
     * @param int $movieId
     * @param int $hallId
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @param float $basePrice
     * @return int Inserted screening_id
     */
    public function create(
        int $movieId,
        int $hallId,
        string $date,
        string $startTime,
        string $endTime,
        float $basePrice
    ): int {
        $sql = "INSERT INTO screenings (movie_id, hall_id, screening_date, start_time, end_time, base_price) 
                VALUES (:movie_id, :hall_id, :screening_date, :start_time, :end_time, :base_price)";

        $this->db->query($sql, [
            ':movie_id'       => $movieId,
            ':hall_id'        => $hallId,
            ':screening_date' => $date,
            ':start_time'     => $startTime,
            ':end_time'       => $endTime,
            ':base_price'     => $basePrice
        ]);

        return (int)$this->db->lastInsertId();
    }
}