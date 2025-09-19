<?php
// api/check-notifications.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/session.php';

checkAuth();

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

try {
    // Check for new messages since last check
    $last_check = $_SESSION['last_notification_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $query = "SELECT COUNT(*) as new_messages FROM messages m
              JOIN user_phone_permissions upp ON (m.to_number IN (SELECT number FROM phone_numbers WHERE id = upp.phone_number_id))
              WHERE upp.user_id = :user_id 
              AND m.direction = 'inbound' 
              AND m.created_at > :last_check";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $current_user['id']);
    $stmt->bindParam(':last_check', $last_check);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['last_notification_check'] = date('Y-m-d H:i:s');
    
    echo json_encode([
        'success' => true,
        'new_messages' => (int)$result['new_messages'],
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check notifications'
    ]);
}
?>
