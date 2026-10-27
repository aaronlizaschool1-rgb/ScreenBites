<?php
/**
 * ScreenBites - Store Screening Schedule Handler
 * Modeled after itim211-2b-shop/item/store.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movieId = (int)($_POST['movie_id'] ?? 0);
    $hallId  = (int)($_POST['hall_id'] ?? 0);
    $date    = trim($_POST['screening_date'] ?? date('Y-m-d'));
    $start   = trim($_POST['start_time'] ?? '13:00');
    $price   = max(1.00, (float)($_POST['price'] ?? 350.00));

    if ($movieId <= 0 || $hallId <= 0) {
        header("Location: " . BASE_URL . "/schedules/create.php");
        exit;
    }

    // Fetch movie duration
    $mRes = mysqli_query($conn, "SELECT duration_minutes FROM movies WHERE movie_id = {$movieId} LIMIT 1");
    $movie = mysqli_fetch_assoc($mRes);
    $duration = (int)($movie['duration_minutes'] ?? 120);

    // Compute end_time = start_time + duration + 20 mins
    $startTimestamp = strtotime($date . ' ' . $start);
    $endTimestamp   = $startTimestamp + (($duration + 20) * 60);
    $endTimeStr     = date('H:i:s', $endTimestamp);

    $dateEsc  = mysqli_real_escape_string($conn, $date);
    $startEsc = mysqli_real_escape_string($conn, $start . ':00');
    $endEsc   = mysqli_real_escape_string($conn, $endTimeStr);

    $sql = "INSERT INTO screenings (movie_id, hall_id, screening_date, start_time, end_time, base_price, created_at)
            VALUES ({$movieId}, {$hallId}, '{$dateEsc}', '{$startEsc}', '{$endEsc}', {$price}, NOW())";

    mysqli_query($conn, $sql);
    header("Location: " . BASE_URL . "/schedules/index.php?date=" . urlencode($date));
    exit;
}

header("Location: " . BASE_URL . "/schedules/index.php");
exit;
