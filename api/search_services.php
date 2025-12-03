<?php
// =====================================================
// STRATHSHARE API - Search Services
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
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $skill_id = isset($_GET['skill_id']) ? intval($_GET['skill_id']) : 0;
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    
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
            AND u.account_status = 'active'";
    
    $params = [];
    
    // Add search filter
    if ($search) {
        $sql .= " AND (sl.title LIKE :search 
                      OR sl.description LIKE :search 
                      OR s.skill_name LIKE :search
                      OR u.first_name LIKE :search
                      OR u.last_name LIKE :search)";
        $params['search'] = "%{$search}%";
    }
    
    // Add skill filter
    if ($skill_id) {
        $sql .= " AND sl.skill_id = :skill_id";
        $params['skill_id'] = $skill_id;
    }
    
    // Add category filter
    if ($category) {
        $sql .= " AND s.category = :category";
        $params['category'] = $category;
    }
    
    $sql .= " ORDER BY u.average_rating DESC, sl.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'services' => $services,
        'count' => count($services),
        'filters' => [
            'search' => $search,
            'skill_id' => $skill_id,
            'category' => $category
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Search Services Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to search services: ' . $e->getMessage()
    ]);
}
?>
