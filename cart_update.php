<?php
/**
 * ScreenBites - Shopping Cart Update Handler
 * Modeled after itim211-2b-shop/cart_update.php
 */

require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$type = $_POST['type'] ?? $_GET['type'] ?? '';
$returnUrl = $_POST['return_url'] ?? $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/view_cart.php');

// 1. Add Concession Snack/Drink
if ($type === 'add_concession') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty       = max(1, (int)($_POST['quantity'] ?? 1));

    if ($productId > 0) {
        $res = mysqli_query($conn, "SELECT product_id, product_name, retail_price, current_stock FROM products WHERE product_id = {$productId} AND is_active = 1 LIMIT 1");
        $item = mysqli_fetch_assoc($res);

        if ($item) {
            $key = 'item_' . $productId;
            if (isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$key] = [
                    'type'     => 'concession',
                    'id'       => (int)$item['product_id'],
                    'name'     => $item['product_name'],
                    'price'    => (float)$item['retail_price'],
                    'qty'      => $qty,
                    'max_qty'  => (int)$item['current_stock'],
                    'details'  => 'Snack / Concession'
                ];
            }
        }
    }
    header("Location: " . $returnUrl);
    exit;
}

// 2. Add Movie Tickets from Seat Grid
if ($type === 'add_tickets') {
    $screeningId = (int)($_POST['screening_id'] ?? 0);
    $selectedSeats = (array)($_POST['seats'] ?? []);

    if ($screeningId > 0 && !empty($selectedSeats)) {
        // Fetch screening info
        $sql = "SELECT s.screening_id, s.start_time, s.screening_date, s.base_price, m.title AS movie_title, h.hall_name 
                FROM screenings s
                INNER JOIN movies m ON s.movie_id = m.movie_id
                INNER JOIN halls h ON s.hall_id = h.hall_id
                WHERE s.screening_id = {$screeningId} LIMIT 1";
        $scRes = mysqli_query($conn, $sql);
        $sc = mysqli_fetch_assoc($scRes);

        if ($sc) {
            $price = (float)$sc['base_price'];
            $timeFmt = date('M d, g:i A', strtotime($sc['screening_date'] . ' ' . $sc['start_time']));

            foreach ($selectedSeats as $seatId) {
                $seatId = (int)$seatId;
                $seatRes = mysqli_query($conn, "SELECT seat_row, seat_number FROM seats WHERE seat_id = {$seatId} LIMIT 1");
                $seat = mysqli_fetch_assoc($seatRes);

                if ($seat) {
                    $seatLabel = $seat['seat_row'] . $seat['seat_number'];
                    $key = "ticket_{$screeningId}_{$seatId}";

                    $_SESSION['cart'][$key] = [
                        'type'         => 'ticket',
                        'screening_id' => $screeningId,
                        'seat_id'      => $seatId,
                        'name'         => $sc['movie_title'] . " (Seat " . $seatLabel . ")",
                        'price'        => $price,
                        'qty'          => 1,
                        'details'      => $sc['hall_name'] . ' • ' . $timeFmt
                    ];
                }
            }
        }
    }
    header("Location: " . BASE_URL . "/view_cart.php");
    exit;
}

// 3. Batch Update Quantities and Remove Items
if ($type === 'update') {
    // Update quantities for concession items
    if (isset($_POST['product_qty']) && is_array($_POST['product_qty'])) {
        foreach ($_POST['product_qty'] as $key => $newQty) {
            $newQty = (int)$newQty;
            if (isset($_SESSION['cart'][$key])) {
                if ($newQty <= 0) {
                    unset($_SESSION['cart'][$key]);
                } elseif ($_SESSION['cart'][$key]['type'] === 'concession') {
                    $_SESSION['cart'][$key]['qty'] = $newQty;
                }
            }
        }
    }

    // Process checkbox removals
    if (isset($_POST['remove_code']) && is_array($_POST['remove_code'])) {
        foreach ($_POST['remove_code'] as $key) {
            unset($_SESSION['cart'][$key]);
        }
    }

    header("Location: " . BASE_URL . "/view_cart.php");
    exit;
}

// 4. Single Remove Link
if ($type === 'remove') {
    $key = $_GET['code'] ?? '';
    if (!empty($key) && isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
    }
    header("Location: " . BASE_URL . "/view_cart.php");
    exit;
}

// Fallback redirect
header("Location: " . BASE_URL . "/view_cart.php");
exit;
