<?php
// webhook-debug.php - Simple webhook diagnostics to find 500 errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Create logs directory if it doesn't exist
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}

function debugLog($message, $data = null) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data
    ];
    file_put_contents('logs/webhook-debug.log', json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

// Start logging
debugLog('=== WEBHOOK DEBUG START ===');
debugLog('PHP Version', phpversion());
debugLog('Request Method', $_SERVER['REQUEST_METHOD']);
debugLog('Content Type', $_SERVER['CONTENT_TYPE'] ?? 'Not set');

try {
    debugLog('Step 1: Basic PHP test', 'OK');
    
    // Test 1: Check if config files exist
    if (file_exists('config/database.php')) {
        debugLog('Step 2: config/database.php exists', 'OK');
        require_once 'config/database.php';
        debugLog('Step 3: config/database.php loaded', 'OK');
    } else {
        debugLog('ERROR: config/database.php not found');
        echo json_encode(['error' => 'config/database.php not found']);
        exit;
    }
    
    // Test 2: Check if class files exist
    $required_files = [
        'classes/Message.php',
        'classes/PhoneNumber.php', 
        'classes/UserPhonePermission.php',
        'includes/functions.php'
    ];
    
    foreach ($required_files as $file) {
        if (file_exists($file)) {
            debugLog("File exists: $file", 'OK');
            try {
                require_once $file;
                debugLog("File loaded: $file", 'OK');
            } catch (Exception $e) {
                debugLog("ERROR loading $file", $e->getMessage());
                echo json_encode(['error' => "Failed to load $file: " . $e->getMessage()]);
                exit;
            }
        } else {
            debugLog("ERROR: File not found: $file");
            echo json_encode(['error' => "File not found: $file"]);
            exit;
        }
    }
    
    // Test 3: Database connection
    try {
        $database = new Database();
        $db = $database->getConnection();
        debugLog('Step 4: Database connection', 'OK');
    } catch (Exception $e) {
        debugLog('ERROR: Database connection failed', $e->getMessage());
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
    
    // Test 4: Get POST data
    $raw_input = file_get_contents('php://input');
    debugLog('Raw input length', strlen($raw_input));
    debugLog('Raw input data', $raw_input);
    debugLog('$_POST data', $_POST);
    
    // Test 5: JSON parsing
    if (!empty($raw_input)) {
        $webhook_data = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugLog('JSON decode error', json_last_error_msg());
            $webhook_data = $_POST;
        } else {
            debugLog('JSON parsed successfully', $webhook_data);
        }
    } else {
        $webhook_data = $_POST;
    }
    
    debugLog('Final webhook data', $webhook_data);
    
    // Test 6: Database queries
    try {
        // Test simple query
        $test_query = "SELECT COUNT(*) as count FROM users";
        $stmt = $db->prepare($test_query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        debugLog('Database query test', "Users count: " . $result['count']);
    } catch (Exception $e) {
        debugLog('ERROR: Database query failed', $e->getMessage());
        echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
        exit;
    }
    
    // Test 7: Check if Message class works
    try {
        $message = new Message($db);
        debugLog('Message class instantiated', 'OK');
    } catch (Exception $e) {
        debugLog('ERROR: Message class failed', $e->getMessage());
        echo json_encode(['error' => 'Message class failed: ' . $e->getMessage()]);
        exit;
    }
    
    // All tests passed
    debugLog('All tests passed', 'SUCCESS');
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook debug completed successfully',
        'php_version' => phpversion(),
        'timestamp' => date('Y-m-d H:i:s'),
        'webhook_data' => $webhook_data
    ]);
    
} catch (Exception $e) {
    debugLog('FATAL ERROR', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Fatal error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

debugLog('=== WEBHOOK DEBUG END ===');
?>