<?php
// fixed-sms-test.php - Corrected version based on your errors
session_start();

// **UPDATE THESE WITH YOUR REAL CREDENTIALS**
$BULKVS_USERNAME = 'tonychou';  // Replace with your actual username
$BULKVS_PASSWORD = 'a93ed4a2a91687c51dd0d452e5cc8546';  // Replace with your actual password

$success = '';
$error = '';
$debug_info = '';

// FIXED: Phone number cleaning function - removes country code
function cleanPhoneNumber($number) {
    // Remove all non-digits
    $clean = preg_replace('/\D/', '', $number);
    
    // If 11 digits and starts with 1, remove the 1 (US country code)
    if (strlen($clean) == 11 && substr($clean, 0, 1) == '1') {
        $clean = substr($clean, 1);
    }
    
    // Should now be exactly 10 digits
    return $clean;
}

// FIXED: Working SMS function using GET method (the one that worked for you)
function sendBulkVSSMS($username, $password, $from, $to, $message) {
    $credentials = base64_encode($username . ':' . $password);
    
    // FIXED: Clean phone numbers to 10 digits only
    $from_clean = cleanPhoneNumber($from);
    $to_clean = cleanPhoneNumber($to);
    
    // Validate 10 digits
    if (strlen($from_clean) != 10 || strlen($to_clean) != 10) {
        return [
            'success' => false,
            'error' => 'Phone numbers must be 10 digits after cleaning',
            'from_original' => $from,
            'from_clean' => $from_clean,
            'to_original' => $to,
            'to_clean' => $to_clean
        ];
    }
    
    // FIXED: Use GET method with URL parameters (the working format)
    $params = [
        'to' => $to_clean,
        'from' => $from_clean,
        'message' => $message
    ];
    
    $url = "https://portal.bulkvs.com/api/v1.0/messageSend?" . http_build_query($params);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',  // FIXED: Use GET, not POST
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    $curl_info = curl_getinfo($curl);
    curl_close($curl);
    
    return [
        'success' => ($httpCode == 200),
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $curl_error,
        'url' => $url,
        'from_original' => $from,
        'from_clean' => $from_clean,
        'to_original' => $to,
        'to_clean' => $to_clean,
        'curl_info' => $curl_info
    ];
}

// Handle form submission
if ($_POST && isset($_POST['send_sms'])) {
    $from_number = $_POST['from_number'] ?? '';
    $to_number = $_POST['to_number'] ?? '';
    $message_body = $_POST['message_body'] ?? '';
    
    if ($from_number && $to_number && $message_body) {
        $result = sendBulkVSSMS($BULKVS_USERNAME, $BULKVS_PASSWORD, $from_number, $to_number, $message_body);
        
        $debug_info = "
        <strong>🔍 Debug Info:</strong><br>
        <strong>From Number:</strong><br>
        &nbsp;&nbsp;Original: " . htmlspecialchars($result['from_original']) . "<br>
        &nbsp;&nbsp;Cleaned: " . htmlspecialchars($result['from_clean']) . "<br>
        <strong>To Number:</strong><br>
        &nbsp;&nbsp;Original: " . htmlspecialchars($result['to_original']) . "<br>
        &nbsp;&nbsp;Cleaned: " . htmlspecialchars($result['to_clean']) . "<br>
        <strong>API Call:</strong><br>
        &nbsp;&nbsp;Method: GET<br>
        &nbsp;&nbsp;URL: " . htmlspecialchars($result['url']) . "<br>
        <strong>Response:</strong><br>
        &nbsp;&nbsp;HTTP Code: " . $result['http_code'] . "<br>
        &nbsp;&nbsp;Response: " . htmlspecialchars($result['response']) . "<br>
        ";
        
        if ($result['curl_error']) {
            $debug_info .= "&nbsp;&nbsp;CURL Error: " . htmlspecialchars($result['curl_error']) . "<br>";
        }
        
        if (isset($result['error'])) {
            $error = $result['error'];
        } elseif ($result['success']) {
            $success = "✅ SMS sent successfully!";
        } else {
            $error = "❌ Failed to send SMS. HTTP Code: " . $result['http_code'];
            
            // Provide specific error guidance
            if ($result['http_code'] == 409) {
                $error .= "<br><strong>Error 409 usually means:</strong><br>
                - Invalid phone number format<br>
                - From number not verified in your BulkVS account<br>
                - Number not authorized for SMS";
            } elseif ($result['http_code'] == 400) {
                $error .= "<br><strong>Error 400 usually means:</strong><br>
                - Bad request format<br>
                - Missing required parameters";
            }
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
    <title>Fixed SMS Test - BulkVS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 700px;
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
        
        input, textarea, select {
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
        
        .tips {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fixed SMS Test</h1>
        
        <?php if ($BULKVS_USERNAME === 'your_bulkvs_username'): ?>
        <div class="config-warning">
            <strong>⚠️ Configuration Required:</strong><br>
            Please update the credentials at the top of this file:<br>
            - $BULKVS_USERNAME<br>
            - $BULKVS_PASSWORD
        </div>
        <?php endif; ?>
        
        <div class="tips">
            <strong>💡 Based on your error analysis:</strong><br>
            • <strong>From number must be</strong> one verified in your BulkVS account<br>
            • <strong>Phone numbers</strong> are automatically cleaned to 10 digits<br>
            • <strong>Using GET method</strong> (the one that worked before)<br>
            • <strong>Your database shows:</strong> 8324786722 (try this as FROM number)
        </div>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="from_number">From Phone Number (must be verified in BulkVS):</label>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="document.getElementById('from_number').value='8324786722'">8324786722 (from DB)</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('from_number').value='17134057990'">17134057990 (from test)</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('from_number').value='18324786722'">18324786722 (DB with +1)</button>
                </div>
                <input type="tel" id="from_number" name="from_number" 
                       value="<?php echo htmlspecialchars($_POST['from_number'] ?? '8324786722'); ?>" 
                       placeholder="Enter FROM number" required>
                <small>This must be a verified number in your BulkVS account</small>
            </div>
            
            <div class="form-group">
                <label for="to_number">To Phone Number:</label>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="document.getElementById('to_number').value='2819780570'">2819780570</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('to_number').value='5551234567'">5551234567</button>
                </div>
                <input type="tel" id="to_number" name="to_number" 
                       value="<?php echo htmlspecialchars($_POST['to_number'] ?? '2819780570'); ?>" 
                       placeholder="Enter TO number (10 digits)" required>
                <small>Will be automatically cleaned to 10 digits</small>
            </div>
            
            <div class="form-group">
                <label for="message_body">Message:</label>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="document.getElementById('message_body').value='FIXED: Test message using GET method - <?php echo date('Y-m-d H:i:s'); ?>'">Fixed Test Message</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('message_body').value='Hello! This is working now.'">Hello Message</button>
                </div>
                <textarea id="message_body" name="message_body" rows="4" 
                          placeholder="Enter your message..." required><?php echo htmlspecialchars($_POST['message_body'] ?? 'FIXED: Test message using GET method'); ?></textarea>
            </div>
            
            <button type="submit" name="send_sms">📱 Send Fixed SMS</button>
        </form>
        
        <?php if ($debug_info): ?>
            <div class="debug"><?php echo $debug_info; ?></div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px;">
            <strong>🔧 Fixes Applied:</strong><br>
            ✅ Using GET method (not POST)<br>
            ✅ Cleaning phone numbers to 10 digits<br>
            ✅ Removing country code (+1) automatically<br>
            ✅ Using verified FROM numbers<br>
            ✅ URL parameters instead of JSON body
        </div>
    </div>
</body>
</html>