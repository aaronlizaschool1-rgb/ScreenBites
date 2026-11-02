<?php
require_once __DIR__ . '/config.php';

define('SEAT_HOLD_DURATION_MINUTES', 8);

function purge_expired_seat_holds(mysqli $conn): int {
    $sql = "DELETE FROM seat_holds WHERE hold_expires_at <= NOW()";
    mysqli_query($conn, $sql);
    return mysqli_affected_rows($conn);
}

function get_active_seat_holds_for_screening(mysqli $conn, int $screeningId): array {
    purge_expired_seat_holds($conn);

    $holds = [];
    $stmt = mysqli_prepare($conn, "SELECT seat_id, hall_id, session_token, hold_expires_at FROM seat_holds WHERE screening_id = ? AND hold_expires_at > NOW()");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $screeningId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $holds[(int)$row['seat_id']] = [
                'seat_id'         => (int)$row['seat_id'],
                'hall_id'         => (int)$row['hall_id'],
                'session_token'   => $row['session_token'],
                'hold_expires_at' => $row['hold_expires_at']
            ];
        }
        mysqli_stmt_close($stmt);
    }
    return $holds;
}

function hold_seat(mysqli $conn, int $screeningId, int $seatId, int $hallId, string $sessionToken): bool {
    purge_expired_seat_holds($conn);

    $chkSold = mysqli_prepare($conn, "SELECT ticket_id FROM tickets WHERE screening_id = ? AND seat_id = ? AND ticket_status = 'Valid' LIMIT 1");
    mysqli_stmt_bind_param($chkSold, "ii", $screeningId, $seatId);
    mysqli_stmt_execute($chkSold);
    $resSold = mysqli_stmt_get_result($chkSold);
    if (mysqli_fetch_assoc($resSold)) {
        mysqli_stmt_close($chkSold);
        return false;
    }
    mysqli_stmt_close($chkSold);

    $chkHold = mysqli_prepare($conn, "SELECT session_token FROM seat_holds WHERE screening_id = ? AND seat_id = ? AND hold_expires_at > NOW() LIMIT 1");
    mysqli_stmt_bind_param($chkHold, "ii", $screeningId, $seatId);
    mysqli_stmt_execute($chkHold);
    $resHold = mysqli_stmt_get_result($chkHold);
    $existing = mysqli_fetch_assoc($resHold);
    mysqli_stmt_close($chkHold);

    if ($existing && $existing['session_token'] !== $sessionToken) {
        return false;
    }

    $duration = SEAT_HOLD_DURATION_MINUTES;
    $sql = "INSERT INTO seat_holds (screening_id, seat_id, hall_id, session_token, hold_expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
            ON DUPLICATE KEY UPDATE
                session_token = VALUES(session_token),
                hold_expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE)";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iiisii", $screeningId, $seatId, $hallId, $sessionToken, $duration, $duration);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    return false;
}

function release_seat_hold(mysqli $conn, int $screeningId, int $seatId): void {
    $stmt = mysqli_prepare($conn, "DELETE FROM seat_holds WHERE screening_id = ? AND seat_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $screeningId, $seatId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function get_session_hold_expiry(mysqli $conn, string $sessionToken): ?int {
    purge_expired_seat_holds($conn);

    $stmt = mysqli_prepare($conn, "SELECT UNIX_TIMESTAMP(MIN(hold_expires_at)) AS earliest_expiry FROM seat_holds WHERE session_token = ? AND hold_expires_at > NOW()");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "s", $sessionToken);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return !empty($row['earliest_expiry']) ? (int)$row['earliest_expiry'] : null;
}

function sync_cart_with_seat_holds(mysqli $conn, string $sessionToken): int {
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return 0;
    }

    purge_expired_seat_holds($conn);

    $activeHolds = [];
    $stmt = mysqli_prepare($conn, "SELECT screening_id, seat_id FROM seat_holds WHERE session_token = ? AND hold_expires_at > NOW()");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $sessionToken);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) {
            $key = $r['screening_id'] . '_' . $r['seat_id'];
            $activeHolds[$key] = true;
        }
        mysqli_stmt_close($stmt);
    }

    $removedCount = 0;
    foreach ($_SESSION['cart'] as $itemKey => $item) {
        if (($item['type'] ?? '') === 'ticket') {
            $scId = (int)$item['screening_id'];
            $stId = (int)$item['seat_id'];
            $holdKey = $scId . '_' . $stId;

            if (!isset($activeHolds[$holdKey])) {
                unset($_SESSION['cart'][$itemKey]);
                $removedCount++;
            }
        }
    }

    return $removedCount;
}
