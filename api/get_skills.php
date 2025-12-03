<?php
// =====================================================
// STRATHSHARE API - Get Skills
// Sean Sabana (220072) & David Mucheru Gachuhi (220235)
// Strathmore University - December 2025
// =====================================================
// SEAN & DAVID — FINAL VERSION 2025

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->query("SELECT skill_id, skill_name, category, description 
                         FROM skills 
                         ORDER BY category, skill_name");
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'skills' => $skills,
        'count' => count($skills)
    ]);
    
} catch (Exception $e) {
    error_log("Get Skills Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch skills: ' . $e->getMessage()
    ]);
}
?>
