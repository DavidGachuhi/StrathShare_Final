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
    
    $provider_id = isset($input['provider_id']) ? intval($input['provider_id']) : 0;
    $skill_id = isset($input['skill_id']) ? intval($input['skill_id']) : 0;
    $title = isset($input['title']) ? trim($input['title']) : '';
    $description = isset($input['description']) ? trim($input['description']) : '';
    $price_min = isset($input['price_min']) ? floatval($input['price_min']) : null;
    $price_max = isset($input['price_max']) ? floatval($input['price_max']) : null;
    
    // Validation
    if (!$provider_id || !$skill_id || !$title || !$description) {
        echo json_encode(['success' => false, 'message' => 'Provider ID, Skill, Title, and Description are required']);
        exit;
    }
    
    if (strlen($title) < 5 || strlen($title) > 100) {
        echo json_encode(['success' => false, 'message' => 'Title must be 5-100 characters']);
        exit;
    }
    
    if (strlen($description) < 20) {
        echo json_encode(['success' => false, 'message' => 'Description must be at least 20 characters']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Verify provider exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND account_status = 'active'");
    $stmt->execute(['user_id' => $provider_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid provider']);
        exit;
    }
    
    // Verify skill exists
    $stmt = $pdo->prepare("SELECT skill_id FROM skills WHERE skill_id = :skill_id");
    $stmt->execute(['skill_id' => $skill_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid skill']);
        exit;
    }
    
    // Create price range string
    $price_range = null;
    if ($price_min !== null && $price_max !== null) {
        $price_range = "KES " . number_format($price_min) . " - " . number_format($price_max);
    } elseif ($price_min !== null) {
        $price_range = "From KES " . number_format($price_min);
    } elseif ($price_max !== null) {
        $price_range = "Up to KES " . number_format($price_max);
    }
    
    // Insert service
    $stmt = $pdo->prepare("INSERT INTO service_listings 
                           (provider_id, skill_id, title, description, price_min, price_max, price_range, availability, status)
                           VALUES (:provider_id, :skill_id, :title, :description, :price_min, :price_max, :price_range, 1, 'active')");
    
    $stmt->execute([
        'provider_id' => $provider_id,
        'skill_id' => $skill_id,
        'title' => $title,
        'description' => $description,
        'price_min' => $price_min,
        'price_max' => $price_max,
        'price_range' => $price_range
    ]);
    
    $listing_id = $pdo->lastInsertId();
    
    // Update user as provider if not already
    $stmt = $pdo->prepare("UPDATE users SET is_provider = 1 WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $provider_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Service created successfully',
        'listing_id' => $listing_id
    ]);
    
} catch (Exception $e) {
    error_log("Create Service Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create service: ' . $e->getMessage()
    ]);
}
?>
