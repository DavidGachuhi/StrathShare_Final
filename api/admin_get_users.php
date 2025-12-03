<?php
// =====================================================
// STRATHSHARE API - Admin Get Users
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
    
    $sql = "SELECT user_id, first_name, last_name, user_email, phone_number, 
                   profile_picture_url, account_status, account_type, 
                   is_admin, is_provider, is_seeker, 
                   average_rating, total_reviews, date_registered, last_login
            FROM users 
            WHERE account_status != 'deleted'
            ORDER BY date_registered DESC";
    
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);
    
} catch (Exception $e) {
    error_log("Admin Get Users Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch users: ' . $e->getMessage()
    ]);
}
?>
