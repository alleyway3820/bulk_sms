<?php
// webhook-clean.php - Webhook with proper message cleanup and decoding
require_once 'config/database.php';
require_once 'classes/Message.php';
require_once 'classes/PhoneNumber.php';
require_once 'classes/UserPhonePermission.php';
require_once 'includes/functions.php';

// Enhanced logging function
function webhookLog($message, $data = null) {
    if (!file_exists('logs')) {
        mkdir('logs', 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data
    ];
    file_put_contents('logs/webhook-clean.log', json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

// Function to clean and decode message content
function cleanMessage($message) {
    if (empty($message)) {
        return $message;
    }
    
    // Step 1: URL decode the message
    $decoded = urldecode($message);
    
    // Step 2: Handle double encoding (sometimes happens)
    $double_decoded = urldecode($decoded);
    if ($double_decoded !== $decoded) {
        $decoded = $double_decoded;
    }
    
    // Step 3: Convert HTML entities if any
    $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Step 4: Normalize whitespace
    $decoded = preg_replace('/\s+/', ' ', $decoded);
    $decoded = trim($decoded);
    
    // Step 5: Ensure UTF-8 encoding is correct
    if (!mb_check_encoding($decoded, 'UTF-8')) {
        $decoded = mb_convert_encoding($decoded, 'UTF-8', 'auto');
    }
    
    return $decoded;
}

// Function to clean phone numbers
function cleanPhoneNumber($number) {
    if (empty($number)) {
        return $number;
    }
    
    // Remove all non-digits
    $clean = preg_replace('/\D/', '', $number);
    
    // Remove country code if 11 digits starting with 1
    if (strlen($clean) == 11 && substr($clean, 0, 1) == '1') {
        $clean = substr($clean, 1);
    }
    
    return $clean;
}

webhookLog('=== WEBHOOK REQUEST START ===');
webhookLog('Request Method', $_SERVER['REQUEST_METHOD']);
webhookLog('Content Type', $_SERVER['CONTENT_TYPE'] ?? 'Not set');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Get raw input
        $raw_input = file_get_contents('php://input');
        webhookLog('Raw input', $raw_input);
        
        // Parse webhook data
        $webhook_data = null;
        if (!empty($raw_input)) {
            $webhook_data = json_decode($raw_input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                webhookLog('JSON decode error', json_last_error_msg());
                $webhook_data = $_POST;
            }
        } else {
            $webhook_data = $_POST;
        }
        
        webhookLog('Parsed webhook data', $webhook_data);
        
        // Extract message data with multiple field name attempts
        $from_number = null;
        $to_number = null;
        $message_body = null;
        $message_id = null;
        
        // Try different field variations
        $possible_from_fields = ['From', 'from', 'FromNumber', 'from_number'];
        $possible_to_fields = ['To', 'to', 'ToNumber', 'to_number'];
        $possible_message_fields = ['Message', 'message', 'Body', 'body', 'text', 'Text'];
        $possible_id_fields = ['MessageId', 'message_id', 'id', 'Id', 'RefId'];
        
        // Extract FROM number
        foreach ($possible_from_fields as $field) {
            if (isset($webhook_data[$field]) && !empty($webhook_data[$field])) {
                $from_number = $webhook_data[$field];
                break;
            }
        }
        
        // Extract TO number (handle array case)
        foreach ($possible_to_fields as $field) {
            if (isset($webhook_data[$field]) && !empty($webhook_data[$field])) {
                $to_value = $webhook_data[$field];
                $to_number = is_array($to_value) ? $to_value[0] : $to_value;
                break;
            }
        }
        
        // Extract MESSAGE body (this is where we clean the encoding)
        foreach ($possible_message_fields as $field) {
            if (isset($webhook_data[$field]) && !empty($webhook_data[$field])) {
                $message_body = $webhook_data[$field];
                break;
            }
        }
        
        // Extract MESSAGE ID
        foreach ($possible_id_fields as $field) {
            if (isset($webhook_data[$field]) && !empty($webhook_data[$field])) {
                $message_id = $webhook_data[$field];
                break;
            }
        }
        
        webhookLog('Raw extracted values', [
            'from_number' => $from_number,
            'to_number' => $to_number,
            'message_body_raw' => $message_body,
            'message_id' => $message_id
        ]);
        
        // CLEAN THE MESSAGE CONTENT
        $message_body_clean = cleanMessage($message_body);
        
        webhookLog('Cleaned message', [
            'original' => $message_body,
            'cleaned' => $message_body_clean
        ]);
        
        // Validate required fields
        if (empty($from_number) || empty($to_number) || empty($message_body_clean)) {
            webhookLog('ERROR - Missing required fields');
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        // Clean phone numbers
        $from_number_clean = cleanPhoneNumber($from_number);
        $to_number_clean = cleanPhoneNumber($to_number);
        
        webhookLog('Cleaned phone numbers', [
            'from_original' => $from_number,
            'from_clean' => $from_number_clean,
            'to_original' => $to_number,
            'to_clean' => $to_number_clean
        ]);
        
        // Find authorized users for this TO number
        $permission_query = "SELECT DISTINCT upp.user_id, u.username, u.email, pn.number, pn.friendly_name
                           FROM user_phone_permissions upp 
                           JOIN users u ON upp.user_id = u.id 
                           JOIN phone_numbers pn ON upp.phone_number_id = pn.id 
                           WHERE pn.number = :to_number 
                           AND upp.can_receive = 1 
                           AND u.is_active = 1 
                           AND pn.is_active = 1";
        
        $permission_stmt = $db->prepare($permission_query);
        $permission_stmt->bindParam(':to_number', $to_number_clean);
        $permission_stmt->execute();
        $authorized_users = $permission_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        webhookLog('Authorized users found', count($authorized_users));
        
        if (empty($authorized_users)) {
            webhookLog('ERROR - No authorized users found');
            http_response_code(404);
            echo json_encode(['error' => 'Number not found or not authorized']);
            exit;
        }
        
        // Create message records for authorized users
        $messages_created = 0;
        foreach ($authorized_users as $user) {
            try {
                $message = new Message($db);
                $message->from_number = $from_number_clean;
                $message->to_number = $to_number_clean;
                $message->message_body = $message_body_clean; // Use the CLEANED message
                $message->direction = 'inbound';
                $message->status = 'delivered';
                $message->bulkvs_message_id = $message_id;
                $message->user_id = $user['user_id'];
                
                if ($message->create()) {
                    $messages_created++;
                    webhookLog('Message created', [
                        'message_id' => $message->id,
                        'user_id' => $user['user_id'],
                        'cleaned_content' => $message_body_clean
                    ]);
                    
                    // Send notifications if functions exist
                    if (function_exists('sendRealTimeNotification')) {
                        sendRealTimeNotification($user['user_id'], 'new_message', [
                            'message_id' => $message->id,
                            'from' => $from_number_clean,
                            'to' => $to_number_clean,
                            'body' => $message_body_clean,
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    if (function_exists('logActivity')) {
                        logActivity($user['user_id'], 'message_received', "From: $from_number_clean");
                    }
                }
            } catch (Exception $e) {
                webhookLog('ERROR creating message', $e->getMessage());
            }
        }
        
        webhookLog('Processing complete', [
            'messages_created' => $messages_created,
            'final_message_content' => $message_body_clean
        ]);
        
        // Return success
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'messages_created' => $messages_created,
            'cleaned_message' => $message_body_clean,
            'original_message' => $message_body
        ]);
        
    } catch (Exception $e) {
        webhookLog('FATAL ERROR', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

webhookLog('=== WEBHOOK REQUEST END ===');
?>