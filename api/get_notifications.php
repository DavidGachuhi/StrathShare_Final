<?php
/**
 * ===================================================================
 * API: Get Notifications for User
 * Returns all notifications for the current user
 * ===================================================================
 * Sean Sabana (220072) & David Mucheru Gachuhi (220235)
 */

// SEAN & DAVID — FINAL VERSION 2025

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit();
}

$user_id = intval($_GET['user_id']);
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $where_clause = "user_id = :user_id";
    if ($unread_only) {
        $where_clause .= " AND is_read = 0";
    }
    
    $query = "SELECT notification_id, type, title, message, reference_id, reference_type, 
                     is_read, created_at
              FROM notifications
              WHERE $where_clause
              ORDER BY created_at DESC
              LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $count_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :user_id AND is_read = 0";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->bindParam(':user_id', $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total' => count($notifications),
        'unread_count' => intval($count_result['unread_count'])
    ]);
    
} catch (PDOException $e) {
    error_log("Get notifications error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
