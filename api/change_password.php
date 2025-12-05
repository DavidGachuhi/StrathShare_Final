<?php


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
    $current_password = isset($input['current_password']) ? $input['current_password'] : '';
    $new_password = isset($input['new_password']) ? $input['new_password'] : '';
    
    if (!$user_id || !$current_password || !$new_password) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    // Validate new password strength
    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    if (!preg_match('/[A-Z]/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain an uppercase letter']);
        exit;
    }
    
    if (!preg_match('/[a-z]/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain a lowercase letter']);
        exit;
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain a special character']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get current password hash
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Hash new password
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id");
    $stmt->execute(['password_hash' => $new_hash, 'user_id' => $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Change Password Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to change password: ' . $e->getMessage()
    ]);
}
?>
