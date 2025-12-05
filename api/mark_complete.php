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
    
    // Get request details with seeker info
    $check_query = "SELECT r.*, 
                           seeker.first_name as seeker_fname, seeker.last_name as seeker_lname,
                           seeker.user_email as seeker_email,
                           provider.first_name as provider_fname, provider.last_name as provider_lname
                    FROM requests r
                    JOIN users seeker ON r.seeker_id = seeker.user_id
                    JOIN users provider ON r.provider_id = provider.user_id
                    WHERE r.request_id = :request_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':request_id', $request_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }
    
    $request = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify provider owns this request
    if ($request['provider_id'] != $provider_id) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this request']);
        exit();
    }
    
    // Can only complete from assigned or in_progress status
    if (!in_array($request['status'], ['assigned', 'in_progress'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot complete request in current status: ' . $request['status']]);
        exit();
    }
    
    // Update status to awaiting_payment
    $update_query = "UPDATE requests 
                     SET status = 'awaiting_payment'
                     WHERE request_id = :request_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':request_id', $request_id);
    
    if ($update_stmt->execute()) {
        // Create notification for seeker
        $notif_query = "INSERT INTO notifications 
                        (user_id, type, title, message, reference_id, reference_type)
                        VALUES (:user_id, 'request_completed', 'Work Completed! ✅', :message, :ref_id, 'request')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $request['seeker_id']);
        $message = $request['provider_fname'] . " has completed work on: " . $request['title'] . ". Please confirm and make payment.";
        $notif_stmt->bindParam(':message', $message);
        $notif_stmt->bindParam(':ref_id', $request_id);
        $notif_stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Request marked as complete. Waiting for seeker to confirm and pay.',
            'new_status' => 'awaiting_payment'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update request status']);
    }
    
} catch (PDOException $e) {
    error_log("Mark complete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
