<?php
// simple-sms-test.php - Working BulkVS SMS Sender using GET method
session_start();

// **UPDATE THESE WITH YOUR REAL CREDENTIALS**
$BULKVS_USERNAME = 'tonychou';  // Replace with your actual username
$BULKVS_PASSWORD = 'a93ed4a2a91687c51dd0d452e5cc8546';  // Replace with your actual password
$FROM_NUMBER = '8324786722';                 // Replace with your verified BulkVS number

$success = '';
$error = '';
$debug_info = '';

// Working SMS function based on previous successful test
function sendBulkVSSMS($username, $password, $from, $to, $message) {
    $credentials = base64_encode($username . ':' . $password);
    
    // Clean phone numbers (10 digits only)
    $from = preg_replace('/\D/', '', $from);
    $to = preg_replace('/\D/', '', $to);
    
    // Build GET URL - this is the format that worked
    $params = [
        'to' => $to,
        'from' => $from,
        'message' => $message
    ];
    
    $url = "https://portal.bulkvs.com/api/v1.0/messageSend?" . http_build_query($params);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',  // Explicitly use GET
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    return [
        'success' => ($httpCode == 200),
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $curl_error,
        'url' => $url,
        'from_clean' => $from,
        'to_clean' => $to
    ];
}

// Handle form submission
if ($_POST && isset($_POST['send_sms'])) {
    $to_number = $_POST['to_number'] ?? '';
    $message_body = $_POST['message_body'] ?? '';
    
    if ($to_number && $message_body) {
        $result = sendBulkVSSMS($BULKVS_USERNAME, $BULKVS_PASSWORD, $FROM_NUMBER, $to_number, $message_body);
        
        $debug_info = "
        <strong>Debug Info:</strong><br>
        URL: " . htmlspecialchars($result['url']) . "<br>
        HTTP Code: " . $result['http_code'] . "<br>
        From (cleaned): " . $result['from_clean'] . "<br>
        To (cleaned): " . $result['to_clean'] . "<br>
        Response: " . htmlspecialchars($result['response']) . "<br>
        ";
        
        if ($result['curl_error']) {
            $debug_info .= "CURL Error: " . htmlspecialchars($result['curl_error']) . "<br>";
        }
        
        if ($result['success']) {
            $success = "✅ SMS sent successfully!";
        } else {
            $error = "❌ Failed to send SMS. HTTP Code: " . $result['http_code'];
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple SMS Test - BulkVS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        button {
            background: #007bff;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        
        button:hover {
            background: #0056b3;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .debug {
            background: #e2e3e5;
            color: #383d41;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px solid #d6d8db;
            font-family: monospace;
            font-size: 12px;
        }
        
        .quick-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .quick-btn {
            background: #6c757d;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .quick-btn:hover {
            background: #545b62;
        }
        
        .config-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Simple SMS Test</h1>
        
        <?php if ($BULKVS_USERNAME === 'your_bulkvs_username'): ?>
        <div class="config-warning">
            <strong>⚠️ Configuration Required:</strong><br>
            Please update the credentials at the top of this file:<br>
            - $BULKVS_USERNAME<br>
            - $BULKVS_PASSWORD<br>
            - $FROM_NUMBER
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="to_number">To Phone Number:</label>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="document.getElementById('to_number').value='2816154820'">281-615-4820</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('to_number').value='5551234567'">555-123-4567</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('to_number').value='<?php echo preg_replace('/\D/', '', $_POST['to_number'] ?? ''); ?>'">Last Used</button>
                </div>
                <input type="tel" id="to_number" name="to_number" 
                       value="<?php echo htmlspecialchars($_POST['to_number'] ?? ''); ?>" 
                       placeholder="Enter phone number (10 digits)" required>
            </div>
            
            <div class="form-group">
                <label for="message_body">Message:</label>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="document.getElementById('message_body').value='Test message from BulkVS API'">Test Message</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('message_body').value='Hello! This is a test SMS.'">Hello Message</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('message_body').value='The time is: <?php echo date('Y-m-d H:i:s'); ?>'">Time Message</button>
                </div>
                <textarea id="message_body" name="message_body" rows="4" 
                          placeholder="Enter your message..." required><?php echo htmlspecialchars($_POST['message_body'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" name="send_sms">📱 Send SMS</button>
        </form>
        
        <?php if ($debug_info): ?>
            <div class="debug"><?php echo $debug_info; ?></div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px;">
            <strong>Current Configuration:</strong><br>
            From Number: <?php echo htmlspecialchars($FROM_NUMBER); ?><br>
            Username: <?php echo htmlspecialchars($BULKVS_USERNAME); ?><br>
            Password: <?php echo str_repeat('*', strlen($BULKVS_PASSWORD)); ?>
        </div>
    </div>
</body>
</html>