<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Total users
    $users_query = "SELECT 
                      COUNT(*) as total_users,
                      SUM(CASE WHEN account_status = 'active' THEN 1 ELSE 0 END) as active_users,
                      SUM(CASE WHEN account_status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
                      SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admin_users,
                      SUM(CASE WHEN DATE(date_registered) = CURDATE() THEN 1 ELSE 0 END) as new_today,
                      SUM(CASE WHEN date_registered >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_this_week
                    FROM users";
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute();
    $user_stats = $users_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total services
    $services_query = "SELECT 
                         COUNT(*) as total_services,
                         SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_services,
                         SUM(CASE WHEN availability = 1 AND status = 'active' THEN 1 ELSE 0 END) as available_services
                       FROM service_listings";
    $services_stmt = $db->prepare($services_query);
    $services_stmt->execute();
    $service_stats = $services_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total requests
    $requests_query = "SELECT 
                         COUNT(*) as total_requests,
                         SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_requests,
                         SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_requests,
                         SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_requests,
                         SUM(CASE WHEN status = 'awaiting_payment' THEN 1 ELSE 0 END) as awaiting_payment,
                         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
                         SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_requests
                       FROM requests";
    $requests_stmt = $db->prepare($requests_query);
    $requests_stmt->execute();
    $request_stats = $requests_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Transaction statistics
    $trans_query = "SELECT 
                      COUNT(*) as total_transactions,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transactions,
                      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                      SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_transactions,
                      COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_revenue,
                      COALESCE(AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END), 0) as avg_transaction
                    FROM transactions";
    $trans_stmt = $db->prepare($trans_query);
    $trans_stmt->execute();
    $transaction_stats = $trans_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Top skills (most services)
    $skills_query = "SELECT s.skill_name, s.category, COUNT(sl.listing_id) as service_count
                     FROM skills s
                     LEFT JOIN service_listings sl ON s.skill_id = sl.skill_id AND sl.status = 'active'
                     GROUP BY s.skill_id
                     ORDER BY service_count DESC
                     LIMIT 5";
    $skills_stmt = $db->prepare($skills_query);
    $skills_stmt->execute();
    $top_skills = $skills_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top providers (by completed transactions)
    $providers_query = "SELECT u.user_id, u.first_name, u.last_name, u.average_rating, u.total_reviews,
                               COUNT(t.transaction_id) as completed_jobs,
                               COALESCE(SUM(t.amount), 0) as total_earned
                        FROM users u
                        LEFT JOIN transactions t ON u.user_id = t.receiver_id AND t.status = 'completed'
                        WHERE u.is_admin = 0
                        GROUP BY u.user_id
                        HAVING completed_jobs > 0
                        ORDER BY completed_jobs DESC, total_earned DESC
                        LIMIT 5";
    $providers_stmt = $db->prepare($providers_query);
    $providers_stmt->execute();
    $top_providers = $providers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent activity
    $recent_query = "SELECT 'registration' as type, 
                            CONCAT(first_name, ' ', last_name, ' registered') as description,
                            date_registered as timestamp
                     FROM users 
                     WHERE is_admin = 0
                     ORDER BY date_registered DESC 
                     LIMIT 5";
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute();
    $recent_activity = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'statistics' => [
            'users' => $user_stats,
            'services' => $service_stats,
            'requests' => $request_stats,
            'transactions' => $transaction_stats,
            'top_skills' => $top_skills,
            'top_providers' => $top_providers,
            'recent_activity' => $recent_activity
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("Admin get stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
