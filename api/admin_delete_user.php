<?php
/**
 * ===================================================================
 * API: Admin - Delete User
 * Permanently removes a user and all their data
 * ===================================================================
 * Sean Sabana (220072) & David Mucheru Gachuhi (220235)
 */

// SEAN & DAVID — FINAL VERSION 2025

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit();
}

$user_id = intval($input['user_id']);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user exists
    $check_query = "SELECT user_id, is_admin, user_email, first_name, last_name FROM users WHERE user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Cannot delete admin accounts
    if ($user['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete admin accounts']);
        exit();
    }
    
    // Cannot delete admin@strathmore.edu
    if ($user['user_email'] === 'admin@strathmore.edu') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete the main admin account']);
        exit();
    }
    
    // Delete user (CASCADE will handle related records)
    $delete_query = "DELETE FROM users WHERE user_id = :user_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':user_id', $user_id);
    
    if ($delete_stmt->execute()) {
        // Log the deletion
        error_log("Admin action: User {$user['user_email']} ({$user['first_name']} {$user['last_name']}, ID: $user_id) has been deleted");
        
        echo json_encode([
            'success' => true,
            'message' => "User {$user['first_name']} {$user['last_name']} has been deleted successfully"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
    
} catch (PDOException $e) {
    error_log("Admin delete user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
