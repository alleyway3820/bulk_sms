<?php
// webhook-simple.php - Minimal webhook to test basic functionality
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to prevent breaking JSON response
ini_set('log_errors', 1);

// Simple logging function
function simpleLog($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
    file_put_contents($logDir . '/simple-webhook.log', $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    simpleLog('=== WEBHOOK REQUEST START ===');
    simpleLog('Method: ' . $_SERVER['REQUEST_METHOD']);
    simpleLog('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));
    simpleLog('Remote IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
    
    // Get raw input
    $raw_input = file_get_contents('php://input');
    simpleLog('Raw input length: ' . strlen($raw_input));
    simpleLog('Raw input: ' . $raw_input);
    
    // Log $_POST data
    simpleLog('$_POST: ' . json_encode($_POST));
    
    // Try to parse JSON
    $webhook_data = null;
    if (!empty($raw_input)) {
        $webhook_data = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            simpleLog('JSON decode error: ' . json_last_error_msg());
            $webhook_data = $_POST;
        } else {
            simpleLog('JSON parsed successfully');
        }
    } else {
        $webhook_data = $_POST;
    }
    
    simpleLog('Final webhook data: ' . json_encode($webhook_data));
    
    // Extract basic message info
    $from = $webhook_data['From'] ?? $webhook_data['from'] ?? 'Unknown';
    $to = $webhook_data['To'] ?? $webhook_data['to'] ?? 'Unknown';
    $message = $webhook_data['Message'] ?? $webhook_data['message'] ?? $webhook_data['Body'] ?? $webhook_data['body'] ?? 'Unknown';
    
    // Handle 'To' as array
    if (is_array($to)) {
        $to = $to[0] ?? 'Unknown';
    }
    
    simpleLog("Extracted - From: $from, To: $to, Message: $message");
    
    // Simple database connection test (optional)
    $db_status = 'Not tested';
    if (file_exists(__DIR__ . '/config/database.php')) {
        try {
            require_once __DIR__ . '/config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            // Simple insert test (you can comment this out if you don't want to save to DB yet)
            /*
            $query = "INSERT INTO messages (from_number, to_number, message_body, direction, status, created_at) 
                      VALUES (:from_number, :to_number, :message_body, 'inbound', 'delivered', NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':from_number', preg_replace('/\D/', '', $from));
            $stmt->bindParam(':to_number', preg_replace('/\D/', '', $to));
            $stmt->bindParam(':message_body', $message);
            $stmt->execute();
            
            $db_status = 'Message saved to database';
            */
            $db_status = 'Database connection OK';
            
        } catch (Exception $e) {
            $db_status = 'Database error: ' . $e->getMessage();
            simpleLog('Database error: ' . $e->getMessage());
        }
    } else {
        $db_status = 'Database config not found';
    }
    
    simpleLog('Database status: ' . $db_status);
    simpleLog('=== WEBHOOK REQUEST SUCCESS ===');
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook received successfully',
        'received_data' => [
            'from' => $from,
            'to' => $to,
            'message' => $message
        ],
        'database_status' => $db_status,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    simpleLog('FATAL ERROR: ' . $e->getMessage());
    simpleLog('File: ' . $e->getFile() . ', Line: ' . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>