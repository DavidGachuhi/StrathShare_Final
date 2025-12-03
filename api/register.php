<?php
// =====================================================
// STRATHSHARE API - User Registration
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
    
    $first_name = isset($input['first_name']) ? trim($input['first_name']) : '';
    $last_name = isset($input['last_name']) ? trim($input['last_name']) : '';
    $email = isset($input['email']) ? trim(strtolower($input['email'])) : '';
    $phone_number = isset($input['phone_number']) ? trim($input['phone_number']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    // Validate names (letters only, 2-50 chars)
    if (!preg_match('/^[a-zA-Z]{2,50}$/', $first_name)) {
        echo json_encode(['success' => false, 'message' => 'First name must be 2-50 letters only']);
        exit;
    }
    
    if (!preg_match('/^[a-zA-Z]{2,50}$/', $last_name)) {
        echo json_encode(['success' => false, 'message' => 'Last name must be 2-50 letters only']);
        exit;
    }
    
    // Validate Strathmore email
    if (!preg_match('/@strathmore\.edu$/i', $email)) {
        echo json_encode(['success' => false, 'message' => 'Please use your @strathmore.edu email address']);
        exit;
    }
    
    // Validate phone number (Kenyan format)
    $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
    if (preg_match('/^07/', $phone_number)) {
        $phone_number = '254' . substr($phone_number, 1);
    }
    if (!preg_match('/^254[0-9]{9}$/', $phone_number)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid Kenyan phone number (254XXXXXXXXX)']);
        exit;
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain an uppercase letter']);
        exit;
    }
    if (!preg_match('/[a-z]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain a lowercase letter']);
        exit;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain a special character']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists']);
        exit;
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Capitalize names properly
    $first_name = ucfirst(strtolower($first_name));
    $last_name = ucfirst(strtolower($last_name));
    
    // Insert new user
    $stmt = $pdo->prepare("INSERT INTO users 
                           (first_name, last_name, user_email, password_hash, phone_number, account_status, account_type)
                           VALUES (:first_name, :last_name, :email, :password_hash, :phone_number, 'active', 'student')");
    
    $stmt->execute([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'password_hash' => $password_hash,
        'phone_number' => $phone_number
    ]);
    
    $user_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully',
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    error_log("Registration Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
}
?>
