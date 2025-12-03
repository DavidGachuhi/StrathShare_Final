<?php
// =====================================================
// STRATHSHARE API - Get My Requests
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
    $role = isset($_GET['role']) ? $_GET['role'] : 'all'; // seeker, provider, or all
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Build query based on role
    if ($role === 'seeker') {
        $sql = "SELECT r.*, 
                       s.skill_name,
                       seeker.first_name as seeker_first_name, 
                       seeker.last_name as seeker_last_name,
                       provider.first_name as provider_first_name, 
                       provider.last_name as provider_last_name
                FROM requests r
                JOIN skills s ON r.skill_id = s.skill_id
                JOIN users seeker ON r.seeker_id = seeker.user_id
                LEFT JOIN users provider ON r.provider_id = provider.user_id
                WHERE r.seeker_id = :user_id
                ORDER BY r.created_at DESC";
    } elseif ($role === 'provider') {
        $sql = "SELECT r.*, 
                       s.skill_name,
                       seeker.first_name as seeker_first_name, 
                       seeker.last_name as seeker_last_name,
                       provider.first_name as provider_first_name, 
                       provider.last_name as provider_last_name
                FROM requests r
                JOIN skills s ON r.skill_id = s.skill_id
                JOIN users seeker ON r.seeker_id = seeker.user_id
                LEFT JOIN users provider ON r.provider_id = provider.user_id
                WHERE r.provider_id = :user_id
                ORDER BY r.created_at DESC";
    } else {
        // All - both seeker and provider requests
        $sql = "SELECT r.*, 
                       s.skill_name,
                       seeker.first_name as seeker_first_name, 
                       seeker.last_name as seeker_last_name,
                       seeker.user_email as seeker_email,
                       provider.first_name as provider_first_name, 
                       provider.last_name as provider_last_name,
                       provider.user_email as provider_email
                FROM requests r
                JOIN skills s ON r.skill_id = s.skill_id
                JOIN users seeker ON r.seeker_id = seeker.user_id
                LEFT JOIN users provider ON r.provider_id = provider.user_id
                WHERE r.seeker_id = ? OR r.provider_id = ?
                ORDER BY r.created_at DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    
    // Use positional parameters for 'all' role, named for others
    if ($role === 'all') {
        $stmt->execute([$user_id, $user_id]);
    } else {
        $stmt->execute(['user_id' => $user_id]);
    }
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'count' => count($requests)
    ]);
    
} catch (Exception $e) {
    error_log("Get My Requests Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch requests: ' . $e->getMessage()
    ]);
}
?>
