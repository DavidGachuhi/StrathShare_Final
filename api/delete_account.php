<?php
// =====================================================
// STRATHSHARE API - Delete Account
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
    
    $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
    $password = isset($input['password']) ? $input['password'] : '';
    
    if (!$user_id || !$password) {
        echo json_encode(['success' => false, 'message' => 'User ID and password are required']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get user info
    $stmt = $pdo->prepare("SELECT password_hash, is_admin, user_email FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Cannot delete admin account
    if ($user['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Admin accounts cannot be deleted']);
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }
    
    // Soft delete - mark as deleted (preserve data for integrity)
    $stmt = $pdo->prepare("UPDATE users SET account_status = 'deleted' WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    
    // Also deactivate all services
    $stmt = $pdo->prepare("UPDATE service_listings SET status = 'deleted' WHERE provider_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    
    // Cancel all open requests
    $stmt = $pdo->prepare("UPDATE requests SET status = 'cancelled' WHERE seeker_id = :user_id AND status = 'open'");
    $stmt->execute(['user_id' => $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Account deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Delete Account Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete account: ' . $e->getMessage()
    ]);
}
?>
