<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get user profile
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, user_email, phone_number, 
                                  profile_picture_url, bio, average_rating, total_reviews,
                                  is_provider, is_seeker, date_registered, last_login
                           FROM users 
                           WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Get service count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM service_listings 
                           WHERE provider_id = :user_id AND status = 'active'");
    $stmt->execute(['user_id' => $user_id]);
    $user['service_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get completed transactions count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions 
                           WHERE (payer_id = ? OR receiver_id = ?) 
                           AND status = 'completed'");
    $stmt->execute([$user_id, $user_id]);
    $user['completed_transactions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Get Profile Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch profile: ' . $e->getMessage()
    ]);
}
?>
