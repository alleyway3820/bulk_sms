<?php
// simple-sms.php - Super Simple SMS Test using GET method
$success = '';
$error = '';
$response_details = '';

// **UPDATE THESE 3 VALUES**
$USERNAME = 'tonychou';
$PASSWORD = 'a93ed4a2a91687c51dd0d452e5cc8546';
$FROM = '8324786722';

if ($_POST) {
    $to = $_POST['to'];
    $message = $_POST['message'];
    
    // Build the GET URL
    $url = "https://portal.bulkvs.com/api/v1.0/messageSend?" . http_build_query([
        'to' => $to,
        'from' => $FROM,
        'message' => $message
    ]);
    
    // Send the request
    $credentials = base64_encode($USERNAME . ':' . $PASSWORD);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $credentials
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Show results
    $response_details = "URL: $url\n\n";
    $response_details .= "HTTP Code: $http_code\n";
    $response_details .= "Response: $response\n";
    
    if ($http_code == 200) {
        $success = "✅ SMS Sent Successfully!";
    } else {
        $error = "❌ Failed to send SMS";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple SMS Test</title>
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
        h1 { 
            color: #333; 
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            font-weight: bold; 
            margin-bottom: 5px;
            color: #555;
        }
        input, textarea { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px;
            font-size: 16px;
        }
        textarea { 
            height: 80px; 
            resize: vertical;
        }
        button { 
            background: #007cba; 
            color: white; 
            padding: 12px 30px; 
            border: none; 
            border-radius: 5px; 
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        button:hover { 
            background: #005a87; 
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 20px 0;
            border: 1px solid #c3e6cb;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 20px 0;
            border: 1px solid #f5c6cb;
        }
        .response { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 5px; 
            font-family: monospace; 
            white-space: pre-wrap;
            border: 1px solid #dee2e6;
            margin-top: 20px;
        }
        .config {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        .quick-fill {
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .quick-btn {
            background: #6c757d;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
            margin: 2px;
        }
        .quick-btn:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Simple SMS Test</h1>
        
        <div class="config">
            <strong>⚙️ Current Config:</strong><br>
            Username: <code><?php echo htmlspecialchars($USERNAME); ?></code><br>
            From Number: <code><?php echo htmlspecialchars($FROM); ?></code><br>
            <small>Update these values at the top of this PHP file</small>
        </div>

        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>📞 To Number (10 digits):</label>
                <input type="text" name="to" value="<?php echo $_POST['to'] ?? '2816154820'; ?>" required>
                <div class="quick-fill">
                    <strong>Quick Fill:</strong>
                    <button type="button" class="quick-btn" onclick="setTo('2816154820')">281-615-4820</button>
                    <button type="button" class="quick-btn" onclick="setTo('5551234567')">555-123-4567</button>
                </div>
            </div>
            
            <div class="form-group">
                <label>💬 Message:</label>
                <textarea name="message" required><?php echo $_POST['message'] ?? 'Hello! Test message from BulkVS 📱'; ?></textarea>
                <div class="quick-fill">
                    <strong>Quick Fill:</strong>
                    <button type="button" class="quick-btn" onclick="setMessage('test')">Test Message</button>
                    <button type="button" class="quick-btn" onclick="setMessage('hello')">Hello Message</button>
                    <button type="button" class="quick-btn" onclick="setMessage('time')">Time Message</button>
                </div>
            </div>
            
            <button type="submit">🚀 Send SMS</button>
        </form>
        
        <?php if ($response_details): ?>
            <div class="response"><?php echo htmlspecialchars($response_details); ?></div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 14px; color: #666;">
            <strong>📋 How this works:</strong><br>
            • Uses GET method (not POST)<br>
            • 10-digit phone numbers (no country code)<br>
            • URL parameters (not JSON)<br>
            • Basic HTTP authentication<br><br>
            
            <strong>✅ Success = HTTP 200</strong><br>
            <strong>❌ Error = HTTP 400/409</strong>
        </div>
    </div>

    <script>
        function setTo(number) {
            document.querySelector('input[name="to"]').value = number;
        }
        
        function setMessage(type) {
            const messages = {
                'test': 'Test message from BulkVS - ' + new Date().toLocaleTimeString(),
                'hello': 'Hello! This is a test message from BulkVS Portal 👋',
                'time': 'Current time: ' + new Date().toLocaleString() + ' 🕐'
            };
            document.querySelector('textarea[name="message"]').value = messages[type];
        }
    </script>
</body>
</html>