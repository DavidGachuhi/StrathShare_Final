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
    
    $sender_id = isset($input['sender_id']) ? intval($input['sender_id']) : 0;
    $receiver_id = isset($input['receiver_id']) ? intval($input['receiver_id']) : 0;
    $message_text = isset($input['message_text']) ? trim($input['message_text']) : '';
    $listing_id = isset($input['listing_id']) ? intval($input['listing_id']) : null;
    $request_id = isset($input['request_id']) ? intval($input['request_id']) : null;
    
    // Validation
    if (!$sender_id || !$receiver_id || !$message_text) {
        echo json_encode(['success' => false, 'message' => 'Sender, receiver, and message are required']);
        exit;
    }
    
    if ($sender_id === $receiver_id) {
        echo json_encode(['success' => false, 'message' => 'Cannot send message to yourself']);
        exit;
    }
    
    if (strlen($message_text) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Message too long (max 2000 characters)']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Verify both users exist
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, user_email FROM users WHERE user_id IN (:sender_id, :receiver_id)");
    $stmt->execute(['sender_id' => $sender_id, 'receiver_id' => $receiver_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) < 2) {
        echo json_encode(['success' => false, 'message' => 'Invalid sender or receiver']);
        exit;
    }
    
    // Get sender and receiver info
    $sender = null;
    $receiver = null;
    foreach ($users as $user) {
        if ($user['user_id'] == $sender_id) $sender = $user;
        if ($user['user_id'] == $receiver_id) $receiver = $user;
    }
    
    // Insert message
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, listing_id, request_id, message_text)
                           VALUES (:sender_id, :receiver_id, :listing_id, :request_id, :message_text)");
    $stmt->execute([
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'listing_id' => $listing_id,
        'request_id' => $request_id,
        'message_text' => $message_text
    ]);
    
    $message_id = $pdo->lastInsertId();
    
    // Create notification for receiver
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type)
                           VALUES (:user_id, 'new_message', :title, :message, :reference_id, 'message')");
    $stmt->execute([
        'user_id' => $receiver_id,
        'title' => 'New Message',
        'message' => "{$sender['first_name']} {$sender['last_name']} sent you a message",
        'reference_id' => $message_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'message_id' => $message_id
    ]);
    
} catch (Exception $e) {
    error_log("Send Message Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send message: ' . $e->getMessage()
    ]);
}
?>
