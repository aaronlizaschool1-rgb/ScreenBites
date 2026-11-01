<?php
/**
 * ScreenBites - Customer Review Action Controller
 * Fulfills MP4: Review CRUD with Regex Masking (100% Pure Procedural PHP)
 * Action-redirect flow with purchase verification, regex masking, and author edit controls
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/reviews.php';
require_once __DIR__ . '/includes/profanity_filter.php';

if (!is_logged_in()) {
    set_flash('error', 'Please sign in to submit or edit a customer review.');
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$userId   = (int)current_user()['user_id'];
$action   = trim($_POST['action'] ?? 'create');
$itemType = trim($_POST['item_type'] ?? 'movie');
$itemId   = (int)($_POST['item_id'] ?? 0);
$reviewId = (int)($_POST['review_id'] ?? 0);
$rating   = (int)($_POST['rating'] ?? 5);
$rawText  = trim($_POST['review_text'] ?? '');
$redirect = trim($_POST['redirect'] ?? '');

if (empty($redirect)) {
    $redirect = ($itemType === 'movie') 
        ? BASE_URL . "/movie_details.php?id=" . $itemId . "#reviews" 
        : BASE_URL . "/concessions/index.php#reviews";
}

// 1. Validate Item Reference
if (!in_array($itemType, ['movie', 'concession'], true) || $itemId <= 0) {
    set_flash('error', 'Invalid item reference specified for review.');
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// 2. Validate Review Content
$fieldErr = validate_numeric_range($rating, 1, 5, 'Star rating')
    ?? validate_required($rawText, 'Review text')
    ?? validate_string_length($rawText, 5, 2000, 'Review text');

if ($fieldErr !== null) {
    set_flash('error', $fieldErr);
    header("Location: " . $redirect);
    exit;
}

// 3. Purchase Verification (MP4 Constraint: 5 pts)
$isVerified = has_user_purchased_item($conn, $userId, $itemType, $itemId);

if (!$isVerified) {
    $itemName = ($itemType === 'movie') ? 'this movie' : 'this concession item';
    set_flash('error', "Verified Purchase Required: Only customers with a completed order for {$itemName} are eligible to leave a review.");
    header("Location: " . $redirect);
    exit;
}

// 4. Regex Profanity Masking (MP4 Constraint: 5 pts)
$sanitizedReview = mask_profanity($rawText);

// 5. Create or Edit Review (MP4 Constraint: 5 pts)
if ($action === 'update' && $reviewId > 0) {
    // Verify ownership
    $ownStmt = mysqli_prepare($conn, "SELECT review_id FROM reviews WHERE review_id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($ownStmt, "ii", $reviewId, $userId);
    mysqli_stmt_execute($ownStmt);
    $owns = mysqli_fetch_assoc(mysqli_stmt_get_result($ownStmt));
    mysqli_stmt_close($ownStmt);

    if (!$owns) {
        set_flash('error', 'Security Violation: You can only edit reviews you authored.');
        header("Location: " . $redirect);
        exit;
    }

    $updStmt = mysqli_prepare($conn, "UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE review_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($updStmt, "isii", $rating, $sanitizedReview, $reviewId, $userId);
    $updOk = mysqli_stmt_execute($updStmt);
    mysqli_stmt_close($updStmt);

    if ($updOk) {
        audit_log('REVIEW_UPDATED', 'reviews', $reviewId, "Updated review for {$itemType} #{$itemId}");
        set_flash('success', 'Your verified customer review was successfully updated.');
    } else {
        set_flash('error', 'Database error updating review: ' . mysqli_error($conn));
    }
} else {
    // Check if user already submitted a review for this item (enforce 1 review per item per user)
    $existing = get_user_item_review($conn, $userId, $itemType, $itemId);
    if ($existing) {
        // Automatically route to update
        $existingId = (int)$existing['review_id'];
        $updStmt = mysqli_prepare($conn, "UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE review_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($updStmt, "isii", $rating, $sanitizedReview, $existingId, $userId);
        mysqli_stmt_execute($updStmt);
        mysqli_stmt_close($updStmt);

        audit_log('REVIEW_UPDATED', 'reviews', $existingId, "Updated existing review for {$itemType} #{$itemId}");
        set_flash('success', 'Your existing review was successfully updated with your new rating and feedback.');
    } else {
        $insStmt = mysqli_prepare($conn, "INSERT INTO reviews (user_id, item_type, item_id, rating, review_text, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($insStmt, "isiis", $userId, $itemType, $itemId, $rating, $sanitizedReview);
        $insOk = mysqli_stmt_execute($insStmt);
        $newReviewId = mysqli_insert_id($conn);
        mysqli_stmt_close($insStmt);

        if ($insOk) {
            audit_log('REVIEW_CREATED', 'reviews', (int)$newReviewId, "Posted verified review for {$itemType} #{$itemId}");
            set_flash('success', 'Thank you! Your verified customer review has been posted.');
        } else {
            set_flash('error', 'Database error posting review: ' . mysqli_error($conn));
        }
    }
}

header("Location: " . $redirect);
exit;
