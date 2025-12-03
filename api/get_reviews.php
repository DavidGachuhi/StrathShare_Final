<?php
// =====================================================
// STRATHSHARE API - Get Reviews
// Sean Sabana (220072) & David Mucheru Gachuhi (220235)
// Strathmore University - December 2025
// =====================================================
// SEAN & DAVID — FINAL VERSION 2025

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get reviews received by this user
    $stmt = $pdo->prepare("SELECT r.*, 
                                  u.first_name as reviewer_first_name,
                                  u.last_name as reviewer_last_name,
                                  u.profile_picture_url as reviewer_picture
                           FROM reviews r
                           JOIN users u ON r.reviewer_id = u.user_id
                           WHERE r.reviewee_id = :user_id
                           ORDER BY r.created_at DESC");
    $stmt->execute(['user_id' => $user_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'count' => count($reviews)
    ]);
    
} catch (Exception $e) {
    error_log("Get Reviews Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch reviews: ' . $e->getMessage()
    ]);
}
?>
