<?php
// webhook-phone-fix.php - Fixed phone number matching
require_once 'config/database.php';
require_once 'classes/Message.php';
require_once 'classes/PhoneNumber.php';
require_once 'classes/UserPhonePermission.php';
require_once 'includes/functions.php';

// Simple logging function
function logMessage($text) {
    if (!file_exists('logs')) {
        mkdir('logs', 0755, true);
    }
    file_put_contents('logs/webhook.log', date('Y-m-d H:i:s') . " - " . $text . "\n", FILE_APPEND | LOCK_EX);
}

// Decode URL encoded messages
function decodeMessage($message) {
    if (empty($message)) {
        return $message;
    }
    
    logMessage("Original message: " . $message);
    $decoded = urldecode($message);
    logMessage("After urldecode: " . $decoded);
    
    if ($decoded !== $message && strpos($decoded, '%') !== false) {
        $decoded = urldecode($decoded);
        logMessage("After double decode: " . $decoded);
    }
    
    $decoded = trim($decoded);
    logMessage("Final decoded message: " . $decoded);
    
    return $decoded;
}

// IMPROVED: Try multiple phone number formats to find a match
function findAuthorizedUsers($db, $to_number) {
    // Clean the number first
    $to_clean = preg_replace('/\D/', '', $to_number);
    
    // Try different variations of the phone number
    $number_variations = [];
    
    // If 11 digits starting with 1, try both with and without the 1
    if (strlen($to_clean) == 11 && substr($to_clean, 0, 1) == '1') {
        $number_variations[] = $to_clean;           // 18324786722
        $number_variations[] = substr($to_clean, 1); // 8324786722
    } else {
        $number_variations[] = $to_clean;           // 8324786722
        $number_variations[] = '1' . $to_clean;    // 18324786722
    }
    
    logMessage("Trying phone number variations: " . implode(', ', $number_variations));
    
    $permission_query = "SELECT DISTINCT upp.user_id, u.username, pn.number, pn.friendly_name
                       FROM user_phone_permissions upp 
                       JOIN users u ON upp.user_id = u.id 
                       JOIN phone_numbers pn ON upp.phone_number_id = pn.id 
                       WHERE pn.number IN (" . str_repeat('?,', count($number_variations) - 1) . "?)
                       AND upp.can_receive = 1 
                       AND u.is_active = 1 
                       AND pn.is_active = 1";
    
    $stmt = $db->prepare($permission_query);
    $stmt->execute($number_variations);
    $authorized_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($authorized_users) . " authorized users");
    
    if (!empty($authorized_users)) {
        logMessage("Matched phone number: " . $authorized_users[0]['number']);
        return ['users' => $authorized_users, 'matched_number' => $authorized_users[0]['number']];
    }
    
    return ['users' => [], 'matched_number' => null];
}

logMessage("=== WEBHOOK REQUEST START ===");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Get the incoming data
        $raw_input = file_get_contents('php://input');
        logMessage("Raw input: " . $raw_input);
        
        $webhook_data = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $webhook_data = $_POST;
        }
        
        logMessage("Parsed data: " . json_encode($webhook_data));
        
        // Extract the basic fields
        $from_number = $webhook_data['From'] ?? $webhook_data['from'] ?? '';
        $to_number = $webhook_data['To'] ?? $webhook_data['to'] ?? '';
        $message_body = $webhook_data['Message'] ?? $webhook_data['message'] ?? $webhook_data['Body'] ?? $webhook_data['body'] ?? '';
        $message_id = $webhook_data['MessageId'] ?? $webhook_data['RefId'] ?? $webhook_data['message_id'] ?? '';
        
        // Handle 'To' as array
        if (is_array($to_number)) {
            $to_number = $to_number[0];
        }
        
        logMessage("Extracted - From: $from_number, To: $to_number, Message: $message_body");
        
        // Decode the message content
        $message_body_decoded = decodeMessage($message_body);
        
        // Clean from number (for storage)
        $from_clean = preg_replace('/\D/', '', $from_number);
        if (strlen($from_clean) == 11 && substr($from_clean, 0, 1) == '1') {
            $from_clean = substr($from_clean, 1);
        }
        
        logMessage("Cleaned from number: $from_clean");
        logMessage("Decoded message: $message_body_decoded");
        
        // IMPROVED: Find authorized users with flexible phone number matching
        $result = findAuthorizedUsers($db, $to_number);
        $authorized_users = $result['users'];
        $matched_number = $result['matched_number'];
        
        if (empty($authorized_users)) {
            // Debug: Show available numbers
            $debug_query = "SELECT pn.number, pn.friendly_name, COUNT(upp.user_id) as user_count 
                           FROM phone_numbers pn 
                           LEFT JOIN user_phone_permissions upp ON pn.id = upp.phone_number_id 
                           WHERE pn.is_active = 1 
                           GROUP BY pn.id";
            $debug_stmt = $db->prepare($debug_query);
            $debug_stmt->execute();
            $available_numbers = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
            logMessage("Available numbers with permissions: " . json_encode($available_numbers));
            
            http_response_code(404);
            echo json_encode([
                'error' => 'No authorized users found', 
                'searched_for' => $to_number,
                'available_numbers' => $available_numbers
            ]);
            exit;
        }
        
        // Create message records using the matched number format
        $messages_created = 0;
        foreach ($authorized_users as $user) {
            try {
                $message = new Message($db);
                $message->from_number = $from_clean;
                $message->to_number = preg_replace('/\D/', '', $matched_number); // Use the format that matched
                $message->message_body = $message_body_decoded;
                $message->direction = 'inbound';
                $message->status = 'delivered';
                $message->bulkvs_message_id = $message_id;
                $message->user_id = $user['user_id'];
                
                if ($message->create()) {
                    $messages_created++;
                    logMessage("Message created for user " . $user['username'] . " (ID: " . $message->id . ")");
                    
                    // Send notifications if functions exist
                    if (function_exists('sendRealTimeNotification')) {
                        sendRealTimeNotification($user['user_id'], 'new_message', [
                            'message_id' => $message->id,
                            'from' => $from_clean,
                            'to' => $message->to_number,
                            'body' => $message_body_decoded,
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        logMessage("Notification sent to user " . $user['user_id']);
                    }
                    
                    if (function_exists('logActivity')) {
                        logActivity($user['user_id'], 'message_received', "From: $from_clean");
                    }
                } else {
                    logMessage("FAILED to create message for user " . $user['username']);
                }
            } catch (Exception $e) {
                logMessage("Error creating message for user " . $user['user_id'] . ": " . $e->getMessage());
            }
        }
        
        logMessage("Successfully created $messages_created messages");
        
        // Return success
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'messages_created' => $messages_created,
            'matched_number' => $matched_number,
            'decoded_message' => $message_body_decoded,
            'users_notified' => count($authorized_users)
        ]);
        
    } catch (Exception $e) {
        logMessage("FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error', 'details' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

logMessage("=== WEBHOOK REQUEST END ===");
?>