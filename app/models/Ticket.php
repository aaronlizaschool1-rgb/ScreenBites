<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class Ticket
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function createTicket(
        int $orderId,
        int $screeningId,
        int $seatId,
        int $hallId,
        float $priceCharged
    ): int {
        $sql = "INSERT INTO tickets (order_id, screening_id, seat_id, hall_id, price_charged, ticket_status)
                VALUES (:order_id, :screening_id, :seat_id, :hall_id, :price_charged, 'Valid')";

        $this->db->query($sql, [
            ':order_id'      => $orderId,
            ':screening_id'  => $screeningId,
            ':seat_id'       => $seatId,
            ':hall_id'       => $hallId,
            ':price_charged' => $priceCharged
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getTicketsByOrderId(int $orderId): array
    {
        $sql = "SELECT t.ticket_id, t.order_id, t.screening_id, t.seat_id, t.price_charged, t.ticket_status,
                       m.title AS movie_title, m.rating AS movie_rating,
                       h.hall_name, sc.screening_date, sc.start_time, sc.end_time,
                       s.seat_row, s.seat_number, st.tier_name
                FROM tickets t
                INNER JOIN screenings sc ON t.screening_id = sc.screening_id
                INNER JOIN movies m ON sc.movie_id = m.movie_id
                INNER JOIN halls h ON t.hall_id = h.hall_id
                INNER JOIN seats s ON t.seat_id = s.seat_id
                INNER JOIN seat_tiers st ON s.tier_id = st.tier_id
                WHERE t.order_id = :order_id";

        return $this->db->fetchAll($sql, [':order_id' => $orderId]);
    }
}
