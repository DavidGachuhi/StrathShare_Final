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

if (!isset($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit();
}

$user_id = intval($input['user_id']);
$notification_id = isset($input['notification_id']) ? intval($input['notification_id']) : null;
$mark_all = isset($input['mark_all']) && $input['mark_all'] === true;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($mark_all) {
        // Mark all notifications as read
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    } elseif ($notification_id) {
        // Mark specific notification as read
        $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = :notif_id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':notif_id', $notification_id);
        $stmt->bindParam(':user_id', $user_id);
    } else {
        echo json_encode(['success' => false, 'message' => 'Specify notification_id or mark_all']);
        exit();
    }
    
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => $affected . ' notification(s) marked as read',
        'count' => $affected
    ]);
    
} catch (PDOException $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
