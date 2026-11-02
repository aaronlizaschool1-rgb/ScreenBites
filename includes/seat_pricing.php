<?php
require_once __DIR__ . '/config.php';

function get_seat_tiers(mysqli $conn): array {
    $tiers = [];
    $res = mysqli_query($conn, "SELECT tier_id, tier_name, tier_surcharge, created_at FROM seat_tiers ORDER BY tier_surcharge ASC, tier_id ASC");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $tId = (int)$row['tier_id'];
            $tiers[$tId] = [
                'tier_id'        => $tId,
                'tier_name'      => $row['tier_name'],
                'tier_surcharge' => (float)$row['tier_surcharge'],
                'created_at'     => $row['created_at']
            ];
        }
    }
    return $tiers;
}

function get_seat_tier_by_id(mysqli $conn, int $tierId): ?array {
    $stmt = mysqli_prepare($conn, "SELECT tier_id, tier_name, tier_surcharge, created_at FROM seat_tiers WHERE tier_id = ? LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "i", $tierId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$row) return null;

    return [
        'tier_id'        => (int)$row['tier_id'],
        'tier_name'      => $row['tier_name'],
        'tier_surcharge' => (float)$row['tier_surcharge'],
        'created_at'     => $row['created_at']
    ];
}

function calculate_seat_price(float $basePrice, float $tierSurcharge): float {
    return round(max(0.00, $basePrice + $tierSurcharge), 2);
}

function get_seats_with_tier_info(mysqli $conn, int $hallId): array {
    $rowsGrouped = [];
    $sql = "SELECT s.seat_id, s.hall_id, s.tier_id, s.seat_row, s.seat_number, s.is_active,
                   st.tier_name, st.tier_surcharge
            FROM seats s
            INNER JOIN seat_tiers st ON s.tier_id = st.tier_id
            WHERE s.hall_id = ? AND s.is_active = 1
            ORDER BY s.seat_row ASC, s.seat_number ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $hallId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($seat = mysqli_fetch_assoc($res)) {
            $r = $seat['seat_row'];
            $rowsGrouped[$r][] = [
                'seat_id'        => (int)$seat['seat_id'],
                'hall_id'        => (int)$seat['hall_id'],
                'tier_id'        => (int)$seat['tier_id'],
                'tier_name'      => $seat['tier_name'],
                'tier_surcharge' => (float)$seat['tier_surcharge'],
                'seat_row'       => $seat['seat_row'],
                'seat_number'    => (int)$seat['seat_number'],
                'label'          => $seat['seat_row'] . $seat['seat_number']
            ];
        }
        mysqli_stmt_close($stmt);
    }
    return $rowsGrouped;
}

function verify_and_price_seats(mysqli $conn, int $screeningId, array $seatIds): array {
    $result = [
        'valid'     => false,
        'error'     => '',
        'screening' => null,
        'seats'     => [],
        'subtotal'  => 0.00
    ];

    if ($screeningId <= 0 || empty($seatIds)) {
        $result['error'] = 'Invalid screening or no seats selected.';
        return $result;
    }

    $sqlSc = "SELECT s.screening_id, s.screening_date, s.start_time, s.end_time, s.base_price, s.hall_id,
                     m.title AS movie_title, h.hall_name
              FROM screenings s
              INNER JOIN movies m ON s.movie_id = m.movie_id
              INNER JOIN halls h ON s.hall_id = h.hall_id
              WHERE s.screening_id = ? LIMIT 1";
    $stmtSc = mysqli_prepare($conn, $sqlSc);
    if (!$stmtSc) {
        $result['error'] = 'Database preparation error.';
        return $result;
    }
    mysqli_stmt_bind_param($stmtSc, "i", $screeningId);
    mysqli_stmt_execute($stmtSc);
    $resSc = mysqli_stmt_get_result($stmtSc);
    $screening = mysqli_fetch_assoc($resSc);
    mysqli_stmt_close($stmtSc);

    if (!$screening) {
        $result['error'] = 'Screening showtime not found.';
        return $result;
    }

    $screeningEndTimestamp = strtotime($screening['screening_date'] . ' ' . $screening['end_time']);
    if ($screeningEndTimestamp && $screeningEndTimestamp < time()) {
        $result['error'] = 'This screening concluded at ' . date('g:i A', $screeningEndTimestamp) . ' and is closed for booking.';
        return $result;
    }

    $result['screening'] = $screening;
    $hallId = (int)$screening['hall_id'];
    $basePrice = (float)$screening['base_price'];

    $soldRes = mysqli_query($conn, "SELECT seat_id FROM tickets WHERE screening_id = {$screeningId} AND ticket_status = 'Valid'");
    $soldIds = [];
    if ($soldRes) {
        while ($sRow = mysqli_fetch_assoc($soldRes)) {
            $soldIds[(int)$sRow['seat_id']] = true;
        }
    }

    $cleanedSeatIds = array_unique(array_filter(array_map('intval', $seatIds)));
    if (empty($cleanedSeatIds)) {
        $result['error'] = 'No valid seat identifiers submitted.';
        return $result;
    }

    $inList = implode(',', $cleanedSeatIds);
    $sqlSeats = "SELECT s.seat_id, s.hall_id, s.tier_id, s.seat_row, s.seat_number,
                        st.tier_name, st.tier_surcharge
                 FROM seats s
                 INNER JOIN seat_tiers st ON s.tier_id = st.tier_id
                 WHERE s.seat_id IN ({$inList}) AND s.is_active = 1";
    $resSeats = mysqli_query($conn, $sqlSeats);

    $seatsMap = [];
    if ($resSeats) {
        while ($st = mysqli_fetch_assoc($resSeats)) {
            $sId = (int)$st['seat_id'];
            $seatsMap[$sId] = $st;
        }
    }

    $subtotal = 0.00;
    $validatedSeats = [];

    foreach ($cleanedSeatIds as $sId) {
        if (!isset($seatsMap[$sId])) {
            $result['error'] = "Seat ID {$sId} does not exist or is inactive.";
            return $result;
        }

        $seatData = $seatsMap[$sId];
        if ((int)$seatData['hall_id'] !== $hallId) {
            $result['error'] = "Seat {$seatData['seat_row']}{$seatData['seat_number']} belongs to a different hall.";
            return $result;
        }

        if (isset($soldIds[$sId])) {
            $result['error'] = "Seat {$seatData['seat_row']}{$seatData['seat_number']} is already reserved or sold.";
            return $result;
        }

        $surcharge = (float)$seatData['tier_surcharge'];
        $unitPrice = calculate_seat_price($basePrice, $surcharge);
        $subtotal += $unitPrice;

        $validatedSeats[$sId] = [
            'seat_id'        => $sId,
            'seat_row'       => $seatData['seat_row'],
            'seat_number'    => (int)$seatData['seat_number'],
            'label'          => $seatData['seat_row'] . $seatData['seat_number'],
            'tier_id'        => (int)$seatData['tier_id'],
            'tier_name'      => $seatData['tier_name'],
            'tier_surcharge' => $surcharge,
            'base_price'     => $basePrice,
            'computed_price' => $unitPrice
        ];
    }

    $result['valid'] = true;
    $result['seats'] = $validatedSeats;
    $result['subtotal'] = round($subtotal, 2);

    return $result;
}
