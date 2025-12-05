<?php


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

if (!isset($input['user_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'User ID and action required']);
    exit();
}

$user_id = intval($input['user_id']);
$action = strtolower(trim($input['action'])); // 'suspend' or 'activate'

if (!in_array($action, ['suspend', 'activate'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action. Use "suspend" or "activate"']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user exists and is not an admin
    $check_query = "SELECT user_id, is_admin, account_status, user_email FROM users WHERE user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Cannot suspend admin accounts
    if ($user['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Cannot suspend admin accounts']);
        exit();
    }
    
    // Set new status
    $new_status = ($action === 'suspend') ? 'suspended' : 'active';
    
    // Check if already in that status
    if ($user['account_status'] === $new_status) {
        echo json_encode(['success' => false, 'message' => 'User is already ' . $new_status]);
        exit();
    }
    
    // Update status
    $update_query = "UPDATE users SET account_status = :status WHERE user_id = :user_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':user_id', $user_id);
    
    if ($update_stmt->execute()) {
        // Log the action
        error_log("Admin action: User {$user['user_email']} (ID: $user_id) has been $new_status");
        
        echo json_encode([
            'success' => true,
            'message' => "User has been {$new_status} successfully",
            'new_status' => $new_status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
    }
    
} catch (PDOException $e) {
    error_log("Admin suspend/activate error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
