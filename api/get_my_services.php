<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;
    
    if (!$provider_id) {
        echo json_encode(['success' => false, 'message' => 'Provider ID is required']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    $sql = "SELECT sl.*, s.skill_name, s.category
            FROM service_listings sl
            JOIN skills s ON sl.skill_id = s.skill_id
            WHERE sl.provider_id = :provider_id
            AND sl.status != 'deleted'
            ORDER BY sl.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['provider_id' => $provider_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'services' => $services,
        'count' => count($services)
    ]);
    
} catch (Exception $e) {
    error_log("Get My Services Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch services: ' . $e->getMessage()
    ]);
}
?>
