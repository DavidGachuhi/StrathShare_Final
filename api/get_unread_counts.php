<?php
/**
 * ===================================================================
 * API: Get Unread Counts
 * Returns unread message count and notification count for badges
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

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get unread messages count
    $msg_query = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = :user_id AND is_read = 0";
    $msg_stmt = $db->prepare($msg_query);
    $msg_stmt->bindParam(':user_id', $user_id);
    $msg_stmt->execute();
    $msg_result = $msg_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get unread notifications count
    $notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0";
    $notif_stmt = $db->prepare($notif_query);
    $notif_stmt->bindParam(':user_id', $user_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get pending requests (for providers) - requests assigned to them that need action
    $pending_query = "SELECT COUNT(*) as count FROM requests 
                      WHERE provider_id = :user_id AND status IN ('assigned', 'in_progress')";
    $pending_stmt = $db->prepare($pending_query);
    $pending_stmt->bindParam(':user_id', $user_id);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get awaiting payment (for seekers) - their requests waiting for payment
    $awaiting_query = "SELECT COUNT(*) as count FROM requests 
                       WHERE seeker_id = :user_id AND status = 'awaiting_payment'";
    $awaiting_stmt = $db->prepare($awaiting_query);
    $awaiting_stmt->bindParam(':user_id', $user_id);
    $awaiting_stmt->execute();
    $awaiting_result = $awaiting_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'unread_messages' => intval($msg_result['count']),
        'unread_notifications' => intval($notif_result['count']),
        'pending_work' => intval($pending_result['count']),
        'awaiting_payment' => intval($awaiting_result['count']),
        'total_badges' => intval($msg_result['count']) + intval($notif_result['count'])
    ]);
    
} catch (PDOException $e) {
    error_log("Get unread counts error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
