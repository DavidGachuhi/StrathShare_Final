<?php
// =====================================================
// STRATHSHARE API - Update Profile
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
    $first_name = isset($input['first_name']) ? trim($input['first_name']) : '';
    $last_name = isset($input['last_name']) ? trim($input['last_name']) : '';
    $phone_number = isset($input['phone_number']) ? trim($input['phone_number']) : null;
    $bio = isset($input['bio']) ? trim($input['bio']) : null;
    $profile_picture_url = isset($input['profile_picture_url']) ? trim($input['profile_picture_url']) : null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    if (empty($first_name) || empty($last_name)) {
        echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
        exit;
    }
    
    // Validate phone number if provided
    if ($phone_number) {
        // Sanitize phone number
        $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Convert 07XX to 254XX
        if (preg_match('/^07/', $phone_number)) {
            $phone_number = '254' . substr($phone_number, 1);
        }
        
        // Validate format
        if (!preg_match('/^254[0-9]{9}$/', $phone_number)) {
            echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Use 254XXXXXXXXX']);
            exit;
        }
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Verify user exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Update profile
    $stmt = $pdo->prepare("UPDATE users 
                           SET first_name = :first_name,
                               last_name = :last_name,
                               phone_number = :phone_number,
                               bio = :bio,
                               profile_picture_url = :profile_picture_url
                           WHERE user_id = :user_id");
    
    $stmt->execute([
        'user_id' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone_number' => $phone_number,
        'bio' => $bio,
        'profile_picture_url' => $profile_picture_url
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Update Profile Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update profile: ' . $e->getMessage()
    ]);
}
?>
