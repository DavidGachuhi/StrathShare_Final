<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get unique conversation partners with latest message - simplified query
    $sql = "SELECT 
                partner_id,
                first_name,
                last_name,
                profile_picture_url,
                last_message,
                last_message_time,
                unread_count
            FROM (
                SELECT 
                    CASE 
                        WHEN m.sender_id = :user_id THEN m.receiver_id 
                        ELSE m.sender_id 
                    END AS partner_id,
                    MAX(m.created_at) as last_msg_time
                FROM messages m
                WHERE m.sender_id = :user_id OR m.receiver_id = :user_id
                GROUP BY partner_id
            ) AS convos
            JOIN users u ON u.user_id = convos.partner_id
            LEFT JOIN LATERAL (
                SELECT message_text, created_at
                FROM messages 
                WHERE (sender_id = :user_id AND receiver_id = convos.partner_id)
                   OR (receiver_id = :user_id AND sender_id = convos.partner_id)
                ORDER BY created_at DESC 
                LIMIT 1
            ) AS last_msg ON TRUE
            LEFT JOIN LATERAL (
                SELECT COUNT(*) as cnt
                FROM messages 
                WHERE sender_id = convos.partner_id 
                  AND receiver_id = :user_id 
                  AND is_read = 0
            ) AS unread ON TRUE
            CROSS JOIN LATERAL (
                SELECT 
                    last_msg.message_text as last_message,
                    last_msg.created_at as last_message_time,
                    COALESCE(unread.cnt, 0) as unread_count
            ) AS combined
            ORDER BY last_message_time DESC";
    
    // Simpler fallback query that works on all MySQL versions
    $sql = "SELECT 
                u.user_id as partner_id,
                u.first_name,
                u.last_name,
                u.profile_picture_url,
                (SELECT m2.message_text 
                 FROM messages m2 
                 WHERE (m2.sender_id = :user_id AND m2.receiver_id = u.user_id)
                    OR (m2.receiver_id = :user_id AND m2.sender_id = u.user_id)
                 ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                (SELECT m3.created_at 
                 FROM messages m3 
                 WHERE (m3.sender_id = :user_id AND m3.receiver_id = u.user_id)
                    OR (m3.receiver_id = :user_id AND m3.sender_id = u.user_id)
                 ORDER BY m3.created_at DESC LIMIT 1) as last_message_time,
                (SELECT COUNT(*) 
                 FROM messages m4 
                 WHERE m4.sender_id = u.user_id 
                   AND m4.receiver_id = :user_id 
                   AND m4.is_read = 0) as unread_count
            FROM users u
            WHERE u.user_id IN (
                SELECT DISTINCT 
                    CASE 
                        WHEN sender_id = :user_id THEN receiver_id 
                        ELSE sender_id 
                    END
                FROM messages 
                WHERE sender_id = :user_id OR receiver_id = :user_id
            )
            ORDER BY last_message_time DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations,
        'count' => count($conversations)
    ]);
    
} catch (Exception $e) {
    error_log("Get Conversations Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch conversations: ' . $e->getMessage()
    ]);
}
?>
