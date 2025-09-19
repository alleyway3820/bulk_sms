<?php
// api/check-new-messages.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/Message.php';
require_once '../includes/session.php';

checkAuth();

$database = new Database();
$db = $database->getConnection();
$message = new Message($db);
$current_user = getCurrentUser();

try {
    $phone_number = $_GET['phone'] ?? '';
    $contact = $_GET['contact'] ?? '';
    $last_message_id = $_GET['last_id'] ?? 0;
    
    if (!$phone_number || !$contact) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit;
    }
    
    // Get new messages since last_message_id
    $query = "SELECT * FROM messages 
              WHERE ((from_number = :contact AND to_number = :phone) OR (from_number = :phone AND to_number = :contact))
              AND id > :last_id
              ORDER BY created_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':contact', $contact);
    $stmt->bindParam(':phone', $phone_number);
    $stmt->bindParam(':last_id', $last_message_id);
    $stmt->execute();
    
    $new_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'messages' => $new_messages,
        'count' => count($new_messages)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check for new messages'
    ]);
}
?>