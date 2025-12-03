<?php
// =====================================================
// STRATHSHARE API - User Login
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
    
    $email = isset($input['email']) ? trim(strtolower($input['email'])) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    
    // Validation
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit;
    }
    
    // Validate Strathmore email
    if (!preg_match('/@strathmore\.edu$/i', $email)) {
        echo json_encode(['success' => false, 'message' => 'Please use your @strathmore.edu email']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Find user
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, user_email, password_hash, 
                                  phone_number, profile_picture_url, bio, account_status,
                                  is_admin, is_provider, is_seeker, average_rating, total_reviews
                           FROM users 
                           WHERE user_email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }
    
    // Check account status
    if ($user['account_status'] === 'suspended') {
        echo json_encode(['success' => false, 'message' => 'Your account has been suspended. Contact admin.']);
        exit;
    }
    
    if ($user['account_status'] === 'deleted') {
        echo json_encode(['success' => false, 'message' => 'This account has been deleted']);
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user['user_id']]);
    
    // Remove password hash from response
    unset($user['password_hash']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Login Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Login failed. Please try again.'
    ]);
}
?>
