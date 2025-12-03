<?php
/**
 * ===================================================================
 * API: Admin - Reset User Password
 * Allows admin to reset any user's password
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

if (!isset($input['user_id']) || !isset($input['new_password'])) {
    echo json_encode(['success' => false, 'message' => 'User ID and new password required']);
    exit();
}

$user_id = intval($input['user_id']);
$new_password = $input['new_password'];

// Validate password strength
if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit();
}

if (!preg_match('/[A-Z]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter']);
    exit();
}

if (!preg_match('/[a-z]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter']);
    exit();
}

if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user exists
    $check_query = "SELECT user_id, user_email, first_name, last_name FROM users WHERE user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Hash new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_query = "UPDATE users SET password_hash = :hash WHERE user_id = :user_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':hash', $password_hash);
    $update_stmt->bindParam(':user_id', $user_id);
    
    if ($update_stmt->execute()) {
        // Log the action
        error_log("Admin action: Password reset for user {$user['user_email']} (ID: $user_id)");
        
        echo json_encode([
            'success' => true,
            'message' => "Password reset successfully for {$user['first_name']} {$user['last_name']}"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
    }
    
} catch (PDOException $e) {
    error_log("Admin reset password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
