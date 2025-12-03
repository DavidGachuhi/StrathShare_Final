<?php
// =====================================================
// STRATHSHARE API - Browse Services
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
                   u.user_id
            FROM service_listings sl
            JOIN skills s ON sl.skill_id = s.skill_id
            JOIN users u ON sl.provider_id = u.user_id
            WHERE sl.status = 'active' 
            AND sl.availability = 1
            AND u.account_status = 'active'
            ORDER BY u.average_rating DESC, sl.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'services' => $services,
        'count' => count($services)
    ]);
    
} catch (Exception $e) {
    error_log("Browse Services Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch services: ' . $e->getMessage()
    ]);
}
?>
