<?php
// =====================================================
// STRATHSHARE API - Delete Service
// Sean Sabana (220072) & David Mucheru Gachuhi (220235)
// Strathmore University - December 2025
// =====================================================
// SEAN & DAVID — FINAL VERSION 2025

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $listing_id = isset($input['listing_id']) ? intval($input['listing_id']) : 0;
    $provider_id = isset($input['provider_id']) ? intval($input['provider_id']) : 0;
    
    if (!$listing_id || !$provider_id) {
        echo json_encode(['success' => false, 'message' => 'Listing ID and Provider ID are required']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT listing_id FROM service_listings 
                           WHERE listing_id = :listing_id AND provider_id = :provider_id");
    $stmt->execute(['listing_id' => $listing_id, 'provider_id' => $provider_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Service not found or you do not own it']);
        exit;
    }
    
    // Soft delete - mark as deleted
    $stmt = $pdo->prepare("UPDATE service_listings SET status = 'deleted' WHERE listing_id = :listing_id");
    $stmt->execute(['listing_id' => $listing_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Service deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Delete Service Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete service: ' . $e->getMessage()
    ]);
}
?>
