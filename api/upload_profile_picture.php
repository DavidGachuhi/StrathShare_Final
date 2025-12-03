<?php
// =====================================================
// STRATHSHARE API - Upload Profile Picture
// Sean Sabana (220072) & David Mucheru Gachuhi (220235)
// Strathmore University - December 2025
// =====================================================
// SEAN & DAVID — FINAL VERSION 2025

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['profile_picture'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $file_type = $finfo->file($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP']);
        exit;
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum 5MB allowed']);
        exit;
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/profile_pictures/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }
    
    // Generate URL
    $picture_url = 'uploads/profile_pictures/' . $filename;
    
    // Update database
    require_once '../config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("UPDATE users SET profile_picture_url = :url WHERE user_id = :user_id");
    $stmt->execute(['url' => $picture_url, 'user_id' => $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture uploaded successfully',
        'picture_url' => $picture_url
    ]);
    
} catch (Exception $e) {
    error_log("Upload Profile Picture Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload picture: ' . $e->getMessage()
    ]);
}
?>
