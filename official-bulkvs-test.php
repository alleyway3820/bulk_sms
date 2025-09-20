<?php
// official-bulkvs-test.php - Using the EXACT format from BulkVS documentation
session_start();

// **UPDATE THESE WITH YOUR REAL CREDENTIALS**
$BULKVS_USERNAME = 'tonychou';  // Replace with your actual username
$BULKVS_PASSWORD = 'a93ed4a2a91687c51dd0d452e5cc8546';  // Replace with your actual password

$success = '';
$error = '';
$debug_info = '';

// Official BulkVS SMS function using their documented format
function sendBulkVSOfficial($username, $password, $from, $to, $message) {
    // Create basic auth header exactly as shown in docs
    $credentials = base64_encode($username . ':' . $password);
    
    // Format phone numbers - try both with and without country code
    $from_formatted = preg_replace('/\D/', '', $from);
    $to_formatted = preg_replace('/\D/', '', $to);
    
    // EXACT JSON structure from BulkVS documentation
    $json_data = [
        "From" => $from_formatted,           // Note: "From" not "from"
        "To" => [$to_formatted],             // Note: "To" as array, not "to"
        "Message" => $message                // Note: "Message" not "message"
    ];
    
    $json_string = json_encode($json_data);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/messageSend",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,                // POST method as documented
        CURLOPT_POSTFIELDS => $json_string,  // JSON body
        CURLOPT_HTTPHEADER => [
            'accept: application/json',                    // Exact headers from docs
            'Content-Type: application/json',
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
        'json_sent' => $json_string,
        'from_formatted' => $from_formatted,
        'to_formatted' => $to_formatted,
        'curl_info' => $curl_info
    ];
}

// Also try alternative formats in case of issues
function sendBulkVSAlternative1($username, $password, $from, $to, $message) {
    // Try with parentheses format like in docs example
    $credentials = base64_encode($username . ':' . $password);
    
    // Format with parentheses: (832) 478-6722
    $from_clean = preg_replace('/\D/', '', $from);
    $to_clean = preg_replace('/\D/', '', $to);
    
    if (strlen($from_clean) == 10) {
        $from_formatted = '(' . substr($from_clean, 0, 3) . ') ' . substr($from_clean, 3, 3) . '-' . substr($from_clean, 6);
    } else {
        $from_formatted = $from_clean;
    }
    
    if (strlen($to_clean) == 10) {
        $to_formatted = '(' . substr($to_clean, 0, 3) . ') ' . substr($to_clean, 3, 3) . '-' . substr($to_clean, 6);
    } else {
        $to_formatted = $to_clean;
    }
    
    $json_data = [
        "From" => $from_formatted,
        "To" => [$to_formatted],
        "Message" => $message
    ];
    
    $json_string = json_encode($json_data);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/messageSend",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_string,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'Content-Type: application/json',
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
        'json_sent' => $json_string,
        'from_formatted' => $from_formatted,
        'to_formatted' => $to_formatted
    ];
}

// Handle form submission
if ($_POST && isset($_POST['send_sms'])) {
    $from_number = $_POST['from_number'] ?? '';
    $to_number = $_POST['to_number'] ?? '';
    $message_body = $_POST['message_body'] ?? '';
    $test_format = $_POST['format'] ?? 'official';
    
    if ($from_number && $to_number && $message_body) {
        if ($test_format === 'formatted') {
            $result = sendBulkVSAlternative1($BULKVS_USERNAME, $BULKVS_PASSWORD, $from_number, $to_number, $message_body);
            $format_used = "Formatted Phone Numbers";
        } else {
            $result = sendBulkVSOfficial($BULKVS_USERNAME, $BULKVS_PASSWORD, $from_number, $to_number, $message_body);
            $format_used = "Official Documentation Format";
        }
        
        $debug_info = "
        <strong>🔍 Debug Info - $format_used:</strong><br>
        <strong>From Number:</strong> " . htmlspecialchars($result['from_formatted']) . "<br>
        <strong>To Number:</strong> " . htmlspecialchars($result['to_formatted']) . "<br>
        <strong>JSON Sent:</strong><br>
        <pre>" . htmlspecialchars($result['json_sent']) . "</pre>
        <strong>Response:</strong><br>
        &nbsp;&nbsp;HTTP Code: " . $result['http_code'] . "<br>
        &nbsp;&nbsp;Response: " . htmlspecialchars($result['response']) . "<br>
        ";
        
        if ($result['curl_error']) {
            $debug_info .= "&nbsp;&nbsp;CURL Error: " . htmlspecialchars($result['curl_error']) . "<br>";
        }
        
        if ($result['success']) {
            $success = "✅ SMS sent successfully using $format_used!";
            
            // Try to parse the success response
            $response_data = json_decode($result['response'], true);
            if ($response_data && isset($response_data['RefId'])) {
                $success .= "<br><strong>Reference ID:</strong> " . htmlspecialchars($response_data['RefId']);
            }
        } else {
            $error = "❌ Failed to send SMS using $format_used. HTTP Code: " . $result['http_code'];
            
            // Parse error response
            $error_data = json_decode($result['response'], true);
            if ($error_data) {
                if (isset($error_data['Code'])) {
                    $error .= "<br><strong>Error Code:</strong> " . htmlspecialchars($error_data['Code']);
                }
                if (isset($error_data['Description'])) {
                    $error .= "<br><strong>Description:</strong> " . htmlspecialchars($error_data['Description']);
                }
            }
            
            // Provide specific guidance
            if ($result['http_code'] == 409) {
                $error .= "<br><br><strong>Troubleshooting 409 Error:</strong><br>
                • Check that FROM number is verified in your BulkVS account<br>
                • Try different phone number formats<br>
                • Ensure account has sufficient credits<br>
                • Verify API credentials are correct";
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
    <title>Official BulkVS Format Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .docs-example {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 14px;
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
            margin-right: 10px;
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
        
        .format-selection {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Official BulkVS API Format Test</h1>
        
        <div class="docs-example">
            <strong>Official BulkVS Documentation Example:</strong><br>
            {<br>
            &nbsp;&nbsp;"From": "(FROM NUMBER)",<br>
            &nbsp;&nbsp;"To": [ "(TO NUMBER)" ],<br>
            &nbsp;&nbsp;"Message": "(UPTO-160-CHARACTER-MESSAGE)"<br>
            }
        </div>
        
        <?php if ($BULKVS_USERNAME === 'your_bulkvs_username'): ?>
        <div class="config-warning">
            <strong>⚠️ Configuration Required:</strong><br>
            Please update the credentials at the top of this file:<br>
            - $BULKVS_USERNAME<br>
            - $BULKVS_PASSWORD
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="format-selection">
                <label for="format">Test Format:</label>
                <select name="format" id="format">
                    <option value="official">Official Format (digits only)</option>
                    <option value="formatted">Formatted Numbers (xxx) xxx-xxxx</option>
                </select>
                <small>Try both formats to see which works with your account</small>
            </div>
            
            <div class="form-group">
                <label for="from_number">From Phone Number:</label>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="document.getElementById('from_number').value='8324786722'">8324786722</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('from_number').value='18324786722'">+1 8324786722</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('from_number').value='(832) 478-6722'">(832) 478-6722</button>
                </div>
                <input type="tel" id="from_number" name="from_number" 
                       value="<?php echo htmlspecialchars($_POST['from_number'] ?? '8324786722'); ?>" 
                       placeholder="Your verified BulkVS number" required>
                <small>Must be verified in your BulkVS account for SMS sending</small>
            </div>
            
            <div class="form-group">
                <label for="to_number">To Phone Number:</label>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="document.getElementById('to_number').value='2819780570'">2819780570</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('to_number').value='5551234567'">5551234567</button>
                    <button type="button" class="quick-btn" onclick="document.getElementById('to_number').value='(281) 978-0570'">(281) 978-0570</button>
                </div>
                <input type="tel" id="to_number" name="to_number" 
                       value="<?php echo htmlspecialchars($_POST['to_number'] ?? '2819780570'); ?>" 
                       placeholder="Destination number" required>
            </div>
            
            <div class="form-group">
                <label for="message_body">Message (160 chars max):</label>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="setMessage('Official BulkVS API test - <?php echo date('H:i:s'); ?>')">Test Message</button>
                    <button type="button" class="quick-btn" onclick="setMessage('Hello from BulkVS!')">Hello</button>
                    <button type="button" class="quick-btn" onclick="setMessage('This message uses the exact BulkVS documentation format.')">Documentation Test</button>
                </div>
                <textarea id="message_body" name="message_body" rows="4" 
                          maxlength="160" placeholder="Enter message (max 160 characters)" 
                          oninput="updateCharCount()" required><?php echo htmlspecialchars($_POST['message_body'] ?? 'Official BulkVS API test'); ?></textarea>
                <div id="charCount" style="text-align: right; font-size: 0.9em; color: #666;">0/160</div>
            </div>
            
            <button type="submit" name="send_sms">📤 Send SMS (Official Format)</button>
        </form>
        
        <?php if ($debug_info): ?>
            <div class="debug"><?php echo $debug_info; ?></div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px;">
            <strong>🔧 Implementation Notes:</strong><br>
            ✅ Uses exact JSON structure from BulkVS docs<br>
            ✅ Proper headers: accept, Content-Type, Authorization<br>
            ✅ POST method with JSON body<br>
            ✅ "From", "To", "Message" field names (capital F, T, M)<br>
            ✅ "To" as array format<br>
            ✅ Tests multiple phone number formats
        </div>
    </div>

    <script>
        function setMessage(text) {
            document.getElementById('message_body').value = text;
            updateCharCount();
        }

        function updateCharCount() {
            const textarea = document.getElementById('message_body');
            const counter = document.getElementById('charCount');
            const length = textarea.value.length;
            counter.textContent = `${length}/160`;
            
            if (length > 160) {
                counter.style.color = '#dc3545';
            } else if (length > 140) {
                counter.style.color = '#ffc107';
            } else {
                counter.style.color = '#666';
            }
        }

        // Initialize character count
        updateCharCount();
    </script>
</body>
</html>