<?php
/**
 * ===================================================================
 * API: Submit Review
 * Both seeker and provider can rate each other after transaction
 * ===================================================================
 * Sean Sabana (220072) & David Mucheru Gachuhi (220235)
 */

// SEAN & DAVID — FINAL VERSION 2025

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once '../config/database.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['transaction_id', 'reviewer_id', 'reviewee_id', 'rating'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$transaction_id = intval($input['transaction_id']);
$reviewer_id = intval($input['reviewer_id']);
$reviewee_id = intval($input['reviewee_id']);
$rating = intval($input['rating']);
$comment = isset($input['comment']) ? trim($input['comment']) : '';

// Validate rating (1-5)
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get transaction and request details
    $trans_query = "SELECT t.*, 
                           r.seeker_id, r.provider_id, r.title as request_title,
                           reviewer.first_name as reviewer_fname, reviewer.last_name as reviewer_lname,
                           reviewee.first_name as reviewee_fname, reviewee.user_email as reviewee_email
                    FROM transactions t
                    JOIN requests r ON t.request_id = r.request_id
                    JOIN users reviewer ON :reviewer_id = reviewer.user_id
                    JOIN users reviewee ON :reviewee_id = reviewee.user_id
                    WHERE t.transaction_id = :transaction_id";
    $trans_stmt = $db->prepare($trans_query);
    $trans_stmt->bindParam(':transaction_id', $transaction_id);
    $trans_stmt->bindParam(':reviewer_id', $reviewer_id);
    $trans_stmt->bindParam(':reviewee_id', $reviewee_id);
    $trans_stmt->execute();
    
    if ($trans_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit();
    }
    
    $transaction = $trans_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify transaction is completed
    if ($transaction['status'] !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Can only review completed transactions']);
        exit();
    }
    
    // Determine review type and validate reviewer/reviewee
    $review_type = '';
    if ($reviewer_id == $transaction['seeker_id'] && $reviewee_id == $transaction['provider_id']) {
        $review_type = 'seeker_to_provider';
    } elseif ($reviewer_id == $transaction['provider_id'] && $reviewee_id == $transaction['seeker_id']) {
        $review_type = 'provider_to_seeker';
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid reviewer/reviewee combination for this transaction']);
        exit();
    }
    
    // Check if review already exists
    $check_query = "SELECT review_id FROM reviews 
                    WHERE transaction_id = :trans_id 
                    AND reviewer_id = :reviewer_id 
                    AND review_type = :review_type";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':trans_id', $transaction_id);
    $check_stmt->bindParam(':reviewer_id', $reviewer_id);
    $check_stmt->bindParam(':review_type', $review_type);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this transaction']);
        exit();
    }
    
    // Get request_id for the review
    $request_id = $transaction['request_id'];
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Insert review
        $insert_query = "INSERT INTO reviews 
                         (transaction_id, request_id, reviewer_id, reviewee_id, rating, comment, review_type)
                         VALUES (:trans_id, :request_id, :reviewer_id, :reviewee_id, :rating, :comment, :review_type)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':trans_id', $transaction_id);
        $insert_stmt->bindParam(':request_id', $request_id);
        $insert_stmt->bindParam(':reviewer_id', $reviewer_id);
        $insert_stmt->bindParam(':reviewee_id', $reviewee_id);
        $insert_stmt->bindParam(':rating', $rating);
        $insert_stmt->bindParam(':comment', $comment);
        $insert_stmt->bindParam(':review_type', $review_type);
        $insert_stmt->execute();
        
        $review_id = $db->lastInsertId();
        
        // Update reviewee's average rating using stored procedure
        $update_rating = "CALL UpdateUserRating(:reviewee_id)";
        $update_stmt = $db->prepare($update_rating);
        $update_stmt->bindParam(':reviewee_id', $reviewee_id);
        $update_stmt->execute();
        $update_stmt->closeCursor(); // Required after stored procedure
        
        // Create notification for reviewee
        $stars = str_repeat('⭐', $rating);
        $notif_query = "INSERT INTO notifications 
                        (user_id, type, title, message, reference_id, reference_type)
                        VALUES (:user_id, 'new_review', :title, :message, :ref_id, 'review')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $reviewee_id);
        $title = "New $rating-Star Review! $stars";
        $notif_stmt->bindParam(':title', $title);
        $message = $transaction['reviewer_fname'] . " left you a " . $rating . "-star review for: " . $transaction['request_title'];
        $notif_stmt->bindParam(':message', $message);
        $notif_stmt->bindParam(':ref_id', $review_id);
        $notif_stmt->execute();
        
        $db->commit();
        
        // Get updated rating
        $rating_query = "SELECT average_rating, total_reviews FROM users WHERE user_id = :user_id";
        $rating_stmt = $db->prepare($rating_query);
        $rating_stmt->bindParam(':user_id', $reviewee_id);
        $rating_stmt->execute();
        $updated_rating = $rating_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully',
            'review_id' => $review_id,
            'new_average_rating' => floatval($updated_rating['average_rating']),
            'total_reviews' => intval($updated_rating['total_reviews'])
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Submit review error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
