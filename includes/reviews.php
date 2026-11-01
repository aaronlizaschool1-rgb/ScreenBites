<?php
/**
 * ScreenBites - Pure Procedural Review Management Subsystem
 * Fulfills MP4: Review CRUD with Regex Masking (100% Pure Procedural PHP)
 * Purchase verification, dynamic review retrieval, and rating aggregations
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/profanity_filter.php';

/**
 * Check if a customer has an existing completed order for a specific movie or concession item
 * Fulfills MP4: Purchase Verification (5 pts)
 *
 * @param mysqli $conn
 * @param int $userId
 * @param string $itemType 'movie' or 'concession'
 * @param int $itemId movie_id or product_id
 * @return bool
 */
function has_user_purchased_item(mysqli $conn, int $userId, string $itemType, int $itemId): bool {
    if ($userId <= 0 || $itemId <= 0) {
        return false;
    }

    if ($itemType === 'movie') {
        // Look up orders matching user_id OR user's registered email with valid ticket for this movie
        $sql = "SELECT COUNT(*) AS purchase_count 
                FROM orders o
                INNER JOIN tickets t ON o.order_id = t.order_id
                INNER JOIN screenings sc ON t.screening_id = sc.screening_id
                WHERE (o.user_id = ? OR o.customer_email = (SELECT email FROM users WHERE user_id = ? AND email IS NOT NULL))
                  AND sc.movie_id = ?
                  AND o.order_status = 'Completed'
                  AND t.ticket_status = 'Valid'";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iii", $userId, $userId, $itemId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);
            return ((int)($row['purchase_count'] ?? 0)) > 0;
        }
    } elseif ($itemType === 'concession') {
        $sql = "SELECT COUNT(*) AS purchase_count 
                FROM orders o
                INNER JOIN order_items oi ON o.order_id = oi.order_id
                WHERE (o.user_id = ? OR o.customer_email = (SELECT email FROM users WHERE user_id = ? AND email IS NOT NULL))
                  AND oi.product_id = ?
                  AND o.order_status = 'Completed'";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iii", $userId, $userId, $itemId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);
            return ((int)($row['purchase_count'] ?? 0)) > 0;
        }
    }

    return false;
}

/**
 * Retrieve all reviews for a specific movie or concession item
 *
 * @param mysqli $conn
 * @param string $itemType
 * @param int $itemId
 * @return array
 */
function get_item_reviews(mysqli $conn, string $itemType, int $itemId): array {
    $reviews = [];
    $sql = "SELECT 
                r.review_id,
                r.user_id,
                r.rating,
                r.review_text,
                r.created_at,
                r.updated_at,
                u.full_name,
                u.username,
                u.avatar_path
            FROM reviews r
            INNER JOIN users u ON r.user_id = u.user_id
            WHERE r.item_type = ? AND r.item_id = ?
            ORDER BY r.created_at DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $itemType, $itemId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $reviews[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $reviews;
}

/**
 * Retrieve average star rating and review count for an item
 *
 * @param mysqli $conn
 * @param string $itemType
 * @param int $itemId
 * @return array ['count' => int, 'average' => float]
 */
function get_item_review_summary(mysqli $conn, string $itemType, int $itemId): array {
    $summary = ['count' => 0, 'average' => 0.0];
    $sql = "SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating), 0) AS avg_rating 
            FROM reviews 
            WHERE item_type = ? AND item_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $itemType, $itemId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $summary['count'] = (int)$row['total_reviews'];
            $summary['average'] = round((float)$row['avg_rating'], 1);
        }
        mysqli_stmt_close($stmt);
    }
    return $summary;
}

/**
 * Retrieve existing review submitted by a specific user for an item (for editing)
 *
 * @param mysqli $conn
 * @param int $userId
 * @param string $itemType
 * @param int $itemId
 * @return array|null
 */
function get_user_item_review(mysqli $conn, int $userId, string $itemType, int $itemId): ?array {
    $sql = "SELECT review_id, rating, review_text, created_at, updated_at 
            FROM reviews 
            WHERE user_id = ? AND item_type = ? AND item_id = ? 
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isi", $userId, $itemType, $itemId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
    return null;
}
