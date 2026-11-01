<?php
/**
 * ScreenBites - Shopping Cart Update Handler
 * Modeled after itim211-2b-shop/cart_update.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seat_pricing.php';
require_once __DIR__ . '/includes/seat_holds.php';

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
            set_flash('success', "Added {$item['product_name']} to cart.");
        }
    }
    header("Location: " . $returnUrl);
    exit;
}

// 1b. Add Concession Combo / Bundle
if ($type === 'add_combo') {
    $comboId = (int)($_POST['combo_id'] ?? 0);
    $qty     = max(1, (int)($_POST['quantity'] ?? 1));

    if ($comboId > 0) {
        $cRes = mysqli_query($conn, "SELECT combo_id, combo_name, description, combo_price FROM combos WHERE combo_id = {$comboId} AND is_active = 1 LIMIT 1");
        $combo = mysqli_fetch_assoc($cRes);

        if ($combo) {
            // Check stock of every constituent product in combo_items
            $ciSql = "SELECT ci.product_id, ci.quantity AS required_qty, p.product_name, p.current_stock
                      FROM combo_items ci
                      INNER JOIN products p ON ci.product_id = p.product_id
                      WHERE ci.combo_id = {$comboId}";
            $ciRes = mysqli_query($conn, $ciSql);

            $itemsList = [];
            $hasStock = true;
            $maxCombosPossible = 9999;

            while ($child = mysqli_fetch_assoc($ciRes)) {
                $neededPerCombo = (int)$child['required_qty'];
                $stock = (int)$child['current_stock'];
                $itemsList[] = "{$neededPerCombo}x " . $child['product_name'];

                $possible = ($neededPerCombo > 0) ? intdiv($stock, $neededPerCombo) : 9999;
                if ($possible < $maxCombosPossible) {
                    $maxCombosPossible = $possible;
                }
                if ($stock < ($neededPerCombo * $qty)) {
                    $hasStock = false;
                }
            }

            if (!$hasStock || $maxCombosPossible <= 0) {
                set_flash('error', "Insufficient stock for items in {$combo['combo_name']}. Only {$maxCombosPossible} available.");
                header("Location: " . $returnUrl);
                exit;
            }

            $key = 'combo_' . $comboId;
            if (isset($_SESSION['cart'][$key])) {
                $newQty = $_SESSION['cart'][$key]['qty'] + $qty;
                if ($newQty > $maxCombosPossible) {
                    set_flash('warning', "Cannot add more {$combo['combo_name']}: maximum available from inventory is {$maxCombosPossible}.");
                    header("Location: " . $returnUrl);
                    exit;
                }
                $_SESSION['cart'][$key]['qty'] = $newQty;
            } else {
                $_SESSION['cart'][$key] = [
                    'type'     => 'combo',
                    'id'       => (int)$combo['combo_id'],
                    'name'     => $combo['combo_name'],
                    'price'    => (float)$combo['combo_price'],
                    'qty'      => $qty,
                    'max_qty'  => $maxCombosPossible,
                    'details'  => 'Includes: ' . implode(', ', $itemsList)
                ];
            }
            set_flash('success', "Added {$combo['combo_name']} bundle to cart.");
        }
    }
    header("Location: " . $returnUrl);
    exit;
}

// 2. Add Movie Tickets from Seat Grid (Tier-Based Anti-Tamper Verification & FR4 Seat Hold)
if ($type === 'add_tickets') {
    $screeningId = (int)($_POST['screening_id'] ?? 0);
    $selectedSeats = (array)($_POST['seats'] ?? []);

    if ($screeningId <= 0 || empty($selectedSeats)) {
        set_flash('error', 'Please select at least one seat to reserve.');
        header("Location: " . ($screeningId > 0 ? BASE_URL . "/ticketing/seat_matrix.php?screening_id={$screeningId}" : BASE_URL . "/index.php"));
        exit;
    }

    $pricedData = verify_and_price_seats($conn, $screeningId, $selectedSeats);

    if (!$pricedData['valid']) {
        set_flash('error', $pricedData['error']);
        header("Location: " . BASE_URL . "/ticketing/seat_matrix.php?screening_id={$screeningId}");
        exit;
    }

    $sc = $pricedData['screening'];
    $timeFmt = date('M d, g:i A', strtotime($sc['screening_date'] . ' ' . $sc['start_time']));
    $sessionToken = session_id();
    $hallId = (int)$sc['hall_id'];

    // Try to hold each seat for 8 minutes
    $heldSeats = [];
    foreach ($pricedData['seats'] as $seatId => $seat) {
        $holdOk = hold_seat($conn, $screeningId, $seatId, $hallId, $sessionToken);
        if (!$holdOk) {
            // Roll back any previously acquired holds for this attempt
            foreach ($heldSeats as $hId) {
                release_seat_hold($conn, $screeningId, $hId);
            }
            set_flash('error', "Seat {$seat['label']} was just held by another customer. Please select another seat.");
            header("Location: " . BASE_URL . "/ticketing/seat_matrix.php?screening_id={$screeningId}");
            exit;
        }
        $heldSeats[] = $seatId;
    }

    foreach ($pricedData['seats'] as $seatId => $seat) {
        $key = "ticket_{$screeningId}_{$seatId}";
        $_SESSION['cart'][$key] = [
            'type'           => 'ticket',
            'screening_id'   => $screeningId,
            'seat_id'        => $seatId,
            'name'           => $sc['movie_title'] . " (Seat " . $seat['label'] . ")",
            'tier_id'        => $seat['tier_id'],
            'tier_name'      => $seat['tier_name'],
            'tier_surcharge' => $seat['tier_surcharge'],
            'price'          => $seat['computed_price'],
            'qty'            => 1,
            'details'        => $sc['hall_name'] . ' • ' . $timeFmt . ' • ' . $seat['tier_name'] . ' Tier'
        ];
    }

    set_flash('success', count($pricedData['seats']) . ' ticket(s) held for 8 minutes and added to your cart.');
    header("Location: " . BASE_URL . "/view_cart.php");
    exit;
}

// 3. Batch Update Quantities (Traditional Form POST - Zero JSON)
if ($type === 'update') {
    if (isset($_POST['product_qty']) && is_array($_POST['product_qty'])) {
        foreach ($_POST['product_qty'] as $key => $newQty) {
            $newQty = (int)$newQty;
            if (isset($_SESSION['cart'][$key])) {
                if ($newQty <= 0) {
                    if ($_SESSION['cart'][$key]['type'] === 'ticket') {
                        $scId = (int)$_SESSION['cart'][$key]['screening_id'];
                        $stId = (int)$_SESSION['cart'][$key]['seat_id'];
                        release_seat_hold($conn, $scId, $stId);
                    }
                    unset($_SESSION['cart'][$key]);
                } elseif ($_SESSION['cart'][$key]['type'] === 'concession' || $_SESSION['cart'][$key]['type'] === 'combo') {
                    $max = (int)($_SESSION['cart'][$key]['max_qty'] ?? 999);
                    $_SESSION['cart'][$key]['qty'] = max(1, min($newQty, $max));
                }
            }
        }
        set_flash('success', 'Cart updated successfully.');
    }

    header("Location: " . BASE_URL . "/view_cart.php");
    exit;
}

// 4. Single Item Removal (Standard GET Link - Zero JSON)
if ($type === 'remove') {
    $key = trim($_GET['code'] ?? '');
    if (!empty($key) && isset($_SESSION['cart'][$key])) {
        if ($_SESSION['cart'][$key]['type'] === 'ticket') {
            $scId = (int)$_SESSION['cart'][$key]['screening_id'];
            $stId = (int)$_SESSION['cart'][$key]['seat_id'];
            release_seat_hold($conn, $scId, $stId);
        }
        $removedName = $_SESSION['cart'][$key]['name'] ?? 'Item';
        unset($_SESSION['cart'][$key]);
        set_flash('success', "{$removedName} removed from cart.");
    }
    header("Location: " . BASE_URL . "/view_cart.php");
    exit;
}

// Fallback redirect
header("Location: " . BASE_URL . "/view_cart.php");
exit;
