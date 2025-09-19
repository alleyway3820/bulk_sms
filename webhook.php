<?php
// webhook.php - BulkVS Webhook Handler
require_once 'config/database.php';
require_once 'classes/Message.php';
require_once 'classes/PhoneNumber.php';
require_once 'classes/UserPhonePermission.php';
require_once 'includes/functions.php';

// Log all incoming webhooks for debugging
$log_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input'),
    'ip' => $_SERVER['REMOTE_ADDR']
];

file_put_contents('logs/webhook.log', json_encode($log_data) . "\n", FILE_APPEND | LOCK_EX);

// Handle BulkVS webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Parse incoming webhook data
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($content_type, 'application/json') !== false) {
            $webhook_data = json_decode(file_get_contents('php://input'), true);
        } else {
            // Handle form-encoded data
            $webhook_data = $_POST;
        }
        
        if (!$webhook_data) {
            http_response_code(400);
            echo "Invalid webhook data";
            exit;
        }
        
        // Extract message data (adjust based on BulkVS webhook format)
        $from_number = $webhook_data['From'] ?? $webhook_data['from'] ?? '';
        $to_number = $webhook_data['To'][0] ?? $webhook_data['to'] ?? '';
        $message_body = $webhook_data['Message'] ?? $webhook_data['message'] ?? $webhook_data['body'] ?? '';
        $message_id = $webhook_data['MessageId'] ?? $webhook_data['id'] ?? '';
        
        if (!$from_number || !$to_number || !$message_body) {
            http_response_code(400);
            echo "Missing required fields";
            exit;
        }
        
        // Clean phone numbers
        $from_number = preg_replace('/\D/', '', $from_number);
        $to_number = preg_replace('/\D/', '', $to_number);
        
        // Find users who have permission to receive messages for this number
        $permission_query = "SELECT DISTINCT upp.user_id, u.username, u.email 
                           FROM user_phone_permissions upp 
                           JOIN users u ON upp.user_id = u.id 
                           JOIN phone_numbers pn ON upp.phone_number_id = pn.id 
                           WHERE pn.number = :to_number 
                           AND upp.can_receive = 1 
                           AND u.is_active = 1 
                           AND pn.is_active = 1";
        
        $permission_stmt = $db->prepare($permission_query);
        $permission_stmt->bindParam(':to_number', $to_number);
        $permission_stmt->execute();
        $authorized_users = $permission_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($authorized_users)) {
            // Log unauthorized message attempt
            error_log("Unauthorized message received for number: $to_number");
            http_response_code(404);
            echo "Number not found or not authorized";
            exit;
        }
        
        // Create message record for each authorized user
        foreach ($authorized_users as $user) {
            $message = new Message($db);
            $message->from_number = $from_number;
            $message->to_number = $to_number;
            $message->message_body = $message_body;
            $message->direction = 'inbound';
            $message->status = 'delivered';
            $message->bulkvs_message_id = $message_id;
            $message->user_id = $user['user_id'];
            
            if ($message->create()) {
                // Send real-time notification
                sendRealTimeNotification($user['user_id'], 'new_message', [
                    'message_id' => $message->id,
                    'from' => $from_number,
                    'to' => $to_number,
                    'body' => $message_body,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                // Log activity
                logActivity($user['user_id'], 'message_received', "From: $from_number");
            }
        }
        
        http_response_code(200);
        echo "OK";
        
    } catch (Exception $e) {
        error_log("Webhook error: " . $e->getMessage());
        http_response_code(500);
        echo "Internal server error";
    }
} else {
    http_response_code(405);
    echo "Method not allowed";
}

?>