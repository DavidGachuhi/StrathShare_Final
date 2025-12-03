<?php
// =====================================================
// STRATHSHARE API - Get Service Details
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
    $listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
    
    if (!$listing_id) {
        echo json_encode(['success' => false, 'message' => 'Listing ID is required']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    $sql = "SELECT sl.*, 
                   s.skill_name, 
                   s.category,
                   u.first_name, 
                   u.last_name, 
                   u.average_rating, 
                   u.total_reviews,
                   u.profile_picture_url,
                   u.bio,
                   u.user_id,
                   u.user_email as provider_email,
                   u.date_registered
            FROM service_listings sl
            JOIN skills s ON sl.skill_id = s.skill_id
            JOIN users u ON sl.provider_id = u.user_id
            WHERE sl.listing_id = :listing_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['listing_id' => $listing_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit;
    }
    
    // Get reviews for this provider
    $stmt = $pdo->prepare("SELECT r.*, 
                                  u.first_name as reviewer_first_name, 
                                  u.last_name as reviewer_last_name
                           FROM reviews r
                           JOIN users u ON r.reviewer_id = u.user_id
                           WHERE r.reviewee_id = :provider_id
                           ORDER BY r.created_at DESC
                           LIMIT 5");
    $stmt->execute(['provider_id' => $service['user_id']]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'service' => $service,
        'reviews' => $reviews
    ]);
    
} catch (Exception $e) {
    error_log("Get Service Details Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch service details: ' . $e->getMessage()
    ]);
}
?>
