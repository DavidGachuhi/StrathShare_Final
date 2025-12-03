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

if (!isset($input['request_id']) || !isset($input['provider_id'])) {
    echo json_encode(['success' => false, 'message' => 'Request ID and Provider ID required']);
    exit();
}

$request_id = intval($input['request_id']);
$provider_id = intval($input['provider_id']);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify request exists and provider is assigned
    $check_query = "SELECT r.*, 
                           seeker.first_name as seeker_fname, seeker.user_email as seeker_email
                    FROM requests r
                    JOIN users seeker ON r.seeker_id = seeker.user_id
                    WHERE r.request_id = :request_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':request_id', $request_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }
    
    $request = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request['provider_id'] != $provider_id) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this request']);
        exit();
    }
    
    if ($request['status'] !== 'assigned') {
        echo json_encode(['success' => false, 'message' => 'Request is not in assigned status. Current status: ' . $request['status']]);
        exit();
    }
    
    // Update status to in_progress
    $update_query = "UPDATE requests 
                     SET status = 'in_progress', started_at = NOW()
                     WHERE request_id = :request_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':request_id', $request_id);
    
    if ($update_stmt->execute()) {
        // Create notification for seeker
        $notif_query = "INSERT INTO notifications 
                        (user_id, type, title, message, reference_id, reference_type)
                        VALUES (:user_id, 'system', 'Work Started', :message, :ref_id, 'request')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $request['seeker_id']);
        $message = "The provider has started working on your request: " . $request['title'];
        $notif_stmt->bindParam(':message', $message);
        $notif_stmt->bindParam(':ref_id', $request_id);
        $notif_stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Work started successfully',
            'new_status' => 'in_progress'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update request status']);
    }
    
} catch (PDOException $e) {
    error_log("Start work error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
