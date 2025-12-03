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
    
    $request_id = isset($input['request_id']) ? intval($input['request_id']) : 0;
    $provider_id = isset($input['provider_id']) ? intval($input['provider_id']) : 0;
    
    if (!$request_id || !$provider_id) {
        echo json_encode(['success' => false, 'message' => 'Request ID and Provider ID are required']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Check if request exists and is open
    $stmt = $pdo->prepare("SELECT r.*, s.skill_name, u.first_name, u.last_name, u.user_email
                           FROM requests r
                           JOIN skills s ON r.skill_id = s.skill_id
                           JOIN users u ON r.seeker_id = u.user_id
                           WHERE r.request_id = :request_id");
    $stmt->execute(['request_id' => $request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    
    if ($request['status'] !== 'open') {
        echo json_encode(['success' => false, 'message' => 'This request is no longer open']);
        exit;
    }
    
    if ($request['seeker_id'] == $provider_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot accept your own request']);
        exit;
    }
    
    // Get provider info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = :provider_id");
    $stmt->execute(['provider_id' => $provider_id]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
        exit;
    }
    
    // Update request status to assigned
    $stmt = $pdo->prepare("UPDATE requests 
                           SET provider_id = :provider_id, 
                               status = 'assigned', 
                               assigned_at = NOW()
                           WHERE request_id = :request_id AND status = 'open'");
    $stmt->execute([
        'provider_id' => $provider_id,
        'request_id' => $request_id
    ]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Failed to accept request. It may have already been taken.']);
        exit;
    }
    
    // Create notification for seeker
    $notification_title = "Request Accepted!";
    $notification_message = "{$provider['first_name']} {$provider['last_name']} has accepted your request: \"{$request['title']}\"";
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type)
                           VALUES (:user_id, 'request_accepted', :title, :message, :reference_id, 'request')");
    $stmt->execute([
        'user_id' => $request['seeker_id'],
        'title' => $notification_title,
        'message' => $notification_message,
        'reference_id' => $request_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Request accepted successfully',
        'new_status' => 'assigned'
    ]);
    
} catch (Exception $e) {
    error_log("Accept Request Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to accept request: ' . $e->getMessage()
    ]);
}
?>
