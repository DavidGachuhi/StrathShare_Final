<?php
// =====================================================
// STRATHSHARE API - Get Open Requests
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
    
    // Get all open requests (no provider assigned yet)
    $sql = "SELECT r.request_id, r.seeker_id, r.title, r.description, r.budget, r.deadline, r.status, r.created_at,
                   s.skill_name,
                   u.first_name, 
                   u.last_name,
                   u.average_rating,
                   r.seeker_id as user_id
            FROM requests r
            JOIN skills s ON r.skill_id = s.skill_id
            JOIN users u ON r.seeker_id = u.user_id
            WHERE r.status = 'open' 
            AND r.provider_id IS NULL
            ORDER BY r.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'count' => count($requests)
    ]);
    
} catch (Exception $e) {
    error_log("Get Requests Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch requests: ' . $e->getMessage()
    ]);
}
?>
