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
    
    $seeker_id = isset($input['seeker_id']) ? intval($input['seeker_id']) : 0;
    $skill_id = isset($input['skill_id']) ? intval($input['skill_id']) : 0;
    $title = isset($input['title']) ? trim($input['title']) : '';
    $description = isset($input['description']) ? trim($input['description']) : '';
    $budget = isset($input['budget']) && $input['budget'] !== '' ? floatval($input['budget']) : null;
    $deadline = isset($input['deadline']) ? trim($input['deadline']) : null;
    $provider_id = isset($input['provider_id']) ? intval($input['provider_id']) : null; // Optional pre-assignment
    
    // Validation
    if (!$seeker_id || !$skill_id || !$title || !$description) {
        echo json_encode(['success' => false, 'message' => 'Seeker ID, Skill, Title, and Description are required']);
        exit;
    }
    
    if (strlen($title) < 5 || strlen($title) > 100) {
        echo json_encode(['success' => false, 'message' => 'Title must be 5-100 characters']);
        exit;
    }
    
    if (strlen($description) < 10) {
        echo json_encode(['success' => false, 'message' => 'Description must be at least 10 characters']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Verify seeker exists
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM users WHERE user_id = :user_id AND account_status = 'active'");
    $stmt->execute(['user_id' => $seeker_id]);
    $seeker = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$seeker) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        exit;
    }
    
    // Verify skill exists
    $stmt = $pdo->prepare("SELECT skill_id, skill_name FROM skills WHERE skill_id = :skill_id");
    $stmt->execute(['skill_id' => $skill_id]);
    $skill = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$skill) {
        echo json_encode(['success' => false, 'message' => 'Invalid skill']);
        exit;
    }
    
    // If provider_id is set, verify provider exists and set status to 'assigned'
    $status = 'open';
    $assigned_at = null;
    if ($provider_id) {
        $stmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM users WHERE user_id = :user_id AND account_status = 'active'");
        $stmt->execute(['user_id' => $provider_id]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$provider) {
            echo json_encode(['success' => false, 'message' => 'Invalid provider']);
            exit;
        }
        if ($provider_id == $seeker_id) {
            echo json_encode(['success' => false, 'message' => 'Cannot request your own service']);
            exit;
        }
        $status = 'assigned';
        $assigned_at = date('Y-m-d H:i:s');
    }
    
    // Insert request
    $stmt = $pdo->prepare("INSERT INTO requests 
                           (seeker_id, provider_id, skill_id, title, description, budget, deadline, status, assigned_at)
                           VALUES (:seeker_id, :provider_id, :skill_id, :title, :description, :budget, :deadline, :status, :assigned_at)");
    
    $stmt->execute([
        'seeker_id' => $seeker_id,
        'provider_id' => $provider_id,
        'skill_id' => $skill_id,
        'title' => $title,
        'description' => $description,
        'budget' => $budget,
        'deadline' => $deadline ?: null,
        'status' => $status,
        'assigned_at' => $assigned_at
    ]);
    
    $request_id = $pdo->lastInsertId();
    
    // Update user as seeker if not already
    $stmt = $pdo->prepare("UPDATE users SET is_seeker = 1 WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $seeker_id]);
    
    // If provider is pre-assigned, create notification for them
    if ($provider_id && isset($provider)) {
        $notif_title = "New Service Request!";
        $notif_message = "{$seeker['first_name']} {$seeker['last_name']} has requested your help with: \"{$title}\"";
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type)
                               VALUES (:user_id, 'request_accepted', :title, :message, :reference_id, 'request')");
        $stmt->execute([
            'user_id' => $provider_id,
            'title' => $notif_title,
            'message' => $notif_message,
            'reference_id' => $request_id
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $provider_id ? 'Request sent to provider' : 'Request posted successfully',
        'request_id' => $request_id,
        'status' => $status
    ]);
    
} catch (Exception $e) {
    error_log("Create Request Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create request: ' . $e->getMessage()
    ]);
}
?>
