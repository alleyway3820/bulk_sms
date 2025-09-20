<?php
// test-sms-fixed.php - Fixed SMS API with correct BulkVS format
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'email' => $_SESSION['email'],
    'role' => $_SESSION['role']
];

$success = '';
$error = '';
$test_results = [];

// **HARDCODED CONFIG - UPDATE THESE VALUES**
$BULKVS_API_USERNAME = 'tonychou';     // Replace with your BulkVS username
$BULKVS_API_PASSWORD = 'a93ed4a2a91687c51dd0d452e5cc8546';     // Replace with your BulkVS password
$FROM_NUMBER = '8324786722';                    // Replace with your BulkVS phone number (10 digits, no country code)

// Multiple API format tests
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'test_multiple_formats') {
    $to_number = '2816154820';  // Target number (10 digits)
    $message_body = 'Test message from BulkVS API - ' . date('Y-m-d H:i:s');
    
    $credentials = base64_encode($BULKVS_API_USERNAME . ':' . $BULKVS_API_PASSWORD);
    
    // Test 1: API Credentials Test
    $test_results[] = "Test 1: API Credentials Test";
    try {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/account",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $test_results[] = "HTTP Code: $httpCode" . "Response: $response";
    } catch (Exception $e) {
        $test_results[] = "Error: " . $e->getMessage();
    }
    
    // Test 2: Original Format (11-digit with array)
    $test_results[] = "\nTest 2: Send Test Message";
    $test_results[] = "From: $FROM_NUMBER" . "To: $to_number" . "Message: $message_body";
    
    $from_11 = '1' . $FROM_NUMBER;
    $to_11 = '1' . $to_number;
    
    $test_results[] = "Cleaned From: $from_11" . "Cleaned To: $to_11";
    
    $data1 = [
        'to' => [$to_11],
        'from' => $from_11,
        'message' => $message_body,
        'method' => 'post'
    ];
    
    $test_results[] = "JSON Data: " . json_encode($data1, JSON_PRETTY_PRINT);
    
    try {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/messageSend",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data1),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $credentials
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_info = curl_getinfo($curl);
        curl_close($curl);
        
        $test_results[] = "**Send Message Results:**" . "HTTP Code: $httpCode" . "Response: $response";
        $test_results[] = "Full CURL Info: " . print_r($curl_info, true);
        
    } catch (Exception $e) {
        $test_results[] = "Error: " . $e->getMessage();
    }
    
    // Test 3: Alternative format (10-digit, no array)
    $test_results[] = "\nTest 3: Alternative API Format";
    $data2 = [
        'to' => $to_number,        // 10 digits, no array
        'from' => $FROM_NUMBER,    // 10 digits
        'message' => $message_body
    ];
    
    $test_results[] = "Alternative JSON Data: " . json_encode($data2, JSON_PRETTY_PRINT);
    
    try {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/messageSend",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data2),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $credentials
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $test_results[] = "**Alternative Format Results:**" . "HTTP Code: $httpCode" . "Response: $response";
        
    } catch (Exception $e) {
        $test_results[] = "Error: " . $e->getMessage();
    }
    
    // Test 4: Check database phone numbers
    $test_results[] = "\nTest 4: Database Phone Numbers";
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=sms_sms", 'sms_sms', 'YOUR_ACTUAL_DB_PASSWORD');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $phone_query = "SELECT * FROM phone_numbers";
        $phone_stmt = $pdo->prepare($phone_query);
        $phone_stmt->execute();
        $phone_numbers = $phone_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $test_results[] = "**Configured Phone Numbers:**";
        foreach ($phone_numbers as $phone) {
            $test_results[] = "ID: {$phone['id']}, Number: {$phone['number']}, Name: {$phone['friendly_name']}";
        }
        
    } catch (Exception $e) {
        $test_results[] = "Database Error: " . $e->getMessage();
    }
    
    // Test 5: Form-encoded format (like Postman)
    $test_results[] = "\nTest 5: Form-Encoded Format";
    $form_data = http_build_query([
        'to' => $to_number,
        'from' => $FROM_NUMBER,
        'message' => $message_body
    ]);
    
    try {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/messageSend",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $form_data,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $credentials
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $test_results[] = "Form Data: $form_data";
        $test_results[] = "**Form-Encoded Results:**" . "HTTP Code: $httpCode" . "Response: $response";
        
    } catch (Exception $e) {
        $test_results[] = "Error: " . $e->getMessage();
    }
    
    // Test 6: GET method test
    $test_results[] = "\nTest 6: GET Method Format";
    $get_url = "https://portal.bulkvs.com/api/v1.0/messageSend?" . http_build_query([
        'to' => $to_number,
        'from' => $FROM_NUMBER,
        'message' => $message_body
    ]);
    
    try {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $get_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $test_results[] = "GET URL: $get_url";
        $test_results[] = "**GET Method Results:**" . "HTTP Code: $httpCode" . "Response: $response";
        
    } catch (Exception $e) {
        $test_results[] = "Error: " . $e->getMessage();
    }
}

// Simple send test
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'send_simple') {
    $to_number = preg_replace('/\D/', '', $_POST['to_number'] ?? '');
    $message_body = trim($_POST['message_body'] ?? '');
    
    if ($to_number && $message_body) {
        $credentials = base64_encode($BULKVS_API_USERNAME . ':' . $BULKVS_API_PASSWORD);
        
        // Try the simplest format first
        $form_data = http_build_query([
            'to' => $to_number,
            'from' => $FROM_NUMBER,
            'message' => $message_body
        ]);
        
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/messageSend",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $form_data,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Authorization: Basic ' . $credentials
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($httpCode == 200) {
                $success = "SMS sent successfully! Response: $response";
            } else {
                $error = "Failed to send SMS. HTTP $httpCode: $response";
            }
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fixed SMS API Test - BulkVS Portal</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 20px;
            font-size: 1.2em;
            font-weight: 600;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: #28a745;
        }

        .btn-warning {
            background: #ffc107;
            color: #000;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-weight: 500;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
        }

        .test-results {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            white-space: pre-wrap;
            max-height: 600px;
            overflow-y: auto;
            line-height: 1.4;
        }

        .config-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .config-info code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Fixed SMS API Test</h1>
            <p>Testing multiple BulkVS API formats to find the correct one</p>
        </div>

        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="config-info">
            <h4>📋 Current Configuration</h4>
            <p><strong>API Username:</strong> <code><?php echo htmlspecialchars($BULKVS_API_USERNAME); ?></code></p>
            <p><strong>From Number:</strong> <code><?php echo htmlspecialchars($FROM_NUMBER); ?></code></p>
            <p><strong>Target Number:</strong> <code>2816154820</code></p>
            <p><strong>Note:</strong> Update the hardcoded values at the top of this file with your real credentials!</p>
        </div>

        <div class="content-grid">
            <!-- Multiple Format Test -->
            <div class="content-card">
                <div class="card-header">🧪 Test All API Formats</div>
                <div class="card-body">
                    <p>This will test 6 different ways to send SMS via BulkVS API:</p>
                    <ul>
                        <li>✅ Credentials test</li>
                        <li>📱 11-digit with array format</li>
                        <li>📱 10-digit simple format</li>
                        <li>📱 Form-encoded format</li>
                        <li>📱 GET method format</li>
                        <li>💾 Database phone numbers</li>
                    </ul>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="test_multiple_formats">
                        <button type="submit" class="btn btn-warning">🚀 Run All Tests</button>
                    </form>
                </div>
            </div>

            <!-- Simple Send -->
            <div class="content-card">
                <div class="card-header">📤 Simple Send Test</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="send_simple">
                        
                        <div class="form-group">
                            <label for="to_number">To Number:</label>
                            <input type="tel" id="to_number" name="to_number" 
                                   value="2816154820" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message_body">Message:</label>
                            <textarea id="message_body" name="message_body" required>Hello! This is a test from BulkVS Portal 📱</textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Send Simple SMS</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if (!empty($test_results)): ?>
        <div class="content-card">
            <div class="card-header">📊 Test Results</div>
            <div class="card-body">
                <div class="test-results"><?php echo htmlspecialchars(implode("\n", $test_results)); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">💡 Troubleshooting Guide</div>
            <div class="card-body">
                <h4>Common BulkVS API Issues:</h4>
                <ul>
                    <li><strong>HTTP 409 - Invalid Phone Number:</strong> Wrong number format or unprovisioned number</li>
                    <li><strong>HTTP 400 - No Valid Content:</strong> Wrong content-type or malformed request</li>
                    <li><strong>HTTP 401:</strong> Invalid credentials</li>
                    <li><strong>HTTP 403:</strong> Insufficient permissions or account suspended</li>
                </ul>
                
                <h4>Phone Number Requirements:</h4>
                <ul>
                    <li>FROM number must be provisioned in your BulkVS account</li>
                    <li>FROM number should be in your database: <code><?php echo htmlspecialchars($FROM_NUMBER); ?></code></li>
                    <li>TO number must be a valid US/Canadian number</li>
                    <li>Some carriers block SMS from certain number types</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>