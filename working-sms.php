<?php
// working-sms.php - SMS Sender using the correct BulkVS format
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
$api_response = '';

// **WORKING CONFIG - UPDATE THESE VALUES**
$BULKVS_API_USERNAME = 'tonychou';     // Replace with your BulkVS username
$BULKVS_API_PASSWORD = 'a93ed4a2a91687c51dd0d452e5cc8546';     // Replace with your BulkVS password
$FROM_NUMBER = '8324786722';                    // Your BulkVS phone number (10 digits)

// Working SMS sending function
function sendSMS($username, $password, $from, $to, $message) {
    $credentials = base64_encode($username . ':' . $password);
    
    // Build GET URL with parameters
    $url = "https://portal.bulkvs.com/api/v1.0/messageSend?" . http_build_query([
        'to' => $to,
        'from' => $from,
        'message' => $message
    ]);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error_info = curl_error($curl);
    curl_close($curl);
    
    return [
        'success' => ($httpCode == 200),
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error_info,
        'url' => $url
    ];
}

// Handle form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'send_sms') {
    $to_number = preg_replace('/\D/', '', $_POST['to_number'] ?? '');
    $message_body = trim($_POST['message_body'] ?? '');
    
    // Ensure 10-digit format (remove country code if present)
    if (strlen($to_number) == 11 && $to_number[0] == '1') {
        $to_number = substr($to_number, 1);
    }
    
    if ($to_number && $message_body && strlen($to_number) == 10) {
        $result = sendSMS($BULKVS_API_USERNAME, $BULKVS_API_PASSWORD, $FROM_NUMBER, $to_number, $message_body);
        
        $api_response = "URL: {$result['url']}\n";
        $api_response .= "HTTP Code: {$result['http_code']}\n";
        $api_response .= "Response: {$result['response']}\n";
        if ($result['error']) {
            $api_response .= "CURL Error: {$result['error']}\n";
        }
        
        if ($result['success']) {
            $success = 'SMS sent successfully to ' . formatPhoneNumber($to_number) . '!';
            
            // Save to database
            try {
                $pdo = new PDO("mysql:host=localhost;dbname=sms_sms", 'sms_sms', 'YOUR_ACTUAL_DB_PASSWORD');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $insert_query = "INSERT INTO messages (from_number, to_number, message_body, direction, status, bulkvs_message_id, user_id, created_at) 
                                 VALUES (?, ?, ?, 'outbound', 'sent', ?, ?, NOW())";
                $insert_stmt = $pdo->prepare($insert_query);
                $insert_stmt->execute([$FROM_NUMBER, $to_number, $message_body, $result['response'], $current_user['id']]);
                
                $api_response .= "\n✅ Message saved to database";
            } catch (Exception $e) {
                $api_response .= "\n⚠️ SMS sent but failed to save to database: " . $e->getMessage();
            }
        } else {
            $error = 'Failed to send SMS. Check the API response below.';
        }
    } else {
        $error = 'Please enter a valid 10-digit phone number and message.';
    }
}

// Helper function to format phone numbers
function formatPhoneNumber($number) {
    $number = preg_replace('/\D/', '', $number);
    if (strlen($number) == 10) {
        return '(' . substr($number, 0, 3) . ') ' . substr($number, 3, 3) . '-' . substr($number, 6);
    }
    return $number;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Working SMS Sender - BulkVS Portal</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .header h1 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 2.5em;
        }

        .header p {
            margin: 0;
            color: #666;
            font-size: 1.1em;
        }

        .content-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 25px;
            font-size: 1.3em;
            font-weight: 600;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 50px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-quick {
            background: #28a745;
            width: auto;
            padding: 10px 20px;
            font-size: 0.9em;
            margin-left: 10px;
        }

        .btn-quick:hover {
            background: #218838;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .alert {
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 1.1em;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .api-response {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 15px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            line-height: 1.5;
        }

        .char-count {
            text-align: right;
            font-size: 0.9em;
            color: #666;
            margin-top: 8px;
        }

        .config-info {
            background: #e7f3ff;
            border: 2px solid #b3d9ff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .config-info h4 {
            margin: 0 0 15px 0;
            color: #0066cc;
        }

        .config-info code {
            background: #f1f8ff;
            padding: 3px 8px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
        }

        .quick-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .quick-btn {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            font-size: 0.85em;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-btn:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .card-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Working SMS Sender</h1>
            <p>Send SMS messages using the correct BulkVS API format</p>
        </div>

        <?php if ($success): ?>
            <div class="alert success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="config-info">
            <h4>📡 Current Configuration</h4>
            <p><strong>API Username:</strong> <code><?php echo htmlspecialchars($BULKVS_API_USERNAME); ?></code></p>
            <p><strong>From Number:</strong> <code><?php echo formatPhoneNumber($FROM_NUMBER); ?></code></p>
            <p><strong>Method:</strong> <code>GET (URL parameters)</code></p>
            <p><strong>Format:</strong> <code>10-digit numbers (no country code)</code></p>
        </div>

        <div class="content-card">
            <div class="card-header">📤 Send SMS Message</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="send_sms">
                    
                    <div class="form-group">
                        <label for="to_number">📱 To Phone Number:</label>
                        <input type="tel" id="to_number" name="to_number" 
                               placeholder="(281) 615-4820" required
                               value="<?php echo htmlspecialchars($_POST['to_number'] ?? '2816154820'); ?>">
                        <div class="quick-buttons">
                            <button type="button" class="quick-btn" onclick="setNumber('2816154820')">
                                📱 (281) 615-4820
                            </button>
                            <button type="button" class="quick-btn" onclick="setNumber('5551234567')">
                                📱 (555) 123-4567
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message_body">💬 Message:</label>
                        <textarea id="message_body" name="message_body" 
                                  placeholder="Enter your message here..." required><?php echo htmlspecialchars($_POST['message_body'] ?? 'Hello! This is a test message from BulkVS Portal. 🚀'); ?></textarea>
                        <div class="char-count">
                            <span id="charCount">0</span>/160 characters
                        </div>
                        <div class="quick-buttons">
                            <button type="button" class="quick-btn" onclick="setMessage('test')">
                                🧪 Test Message
                            </button>
                            <button type="button" class="quick-btn" onclick="setMessage('hello')">
                                👋 Hello Message
                            </button>
                            <button type="button" class="quick-btn" onclick="setMessage('time')">
                                ⏰ Time Message
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">🚀 Send SMS Message</button>
                </form>
            </div>
        </div>

        <?php if ($api_response): ?>
        <div class="content-card">
            <div class="card-header">📊 API Response</div>
            <div class="card-body">
                <div class="api-response"><?php echo htmlspecialchars($api_response); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">✅ Success! Working Format Found</div>
            <div class="card-body">
                <h4>🎉 The correct BulkVS API format is:</h4>
                <ul style="line-height: 1.8;">
                    <li><strong>Method:</strong> GET (not POST)</li>
                    <li><strong>Phone Numbers:</strong> 10 digits (no country code)</li>
                    <li><strong>Parameters:</strong> URL query string (not JSON body)</li>
                    <li><strong>From Number:</strong> Must be provisioned in your BulkVS account</li>
                </ul>
                
                <h4>📋 Setup Checklist:</h4>
                <ol style="line-height: 1.8;">
                    <li>✅ API credentials work (HTTP 200 on account test)</li>
                    <li>✅ GET method works (HTTP 200 on message send)</li>
                    <li>✅ 10-digit phone numbers work</li>
                    <li>⚠️ Update database password to save messages</li>
                </ol>
                
                <h4>🔧 Next Steps:</h4>
                <ol style="line-height: 1.8;">
                    <li>Update the config values at the top of this file</li>
                    <li>Fix the database password to enable message logging</li>
                    <li>Update your main messaging system to use GET method</li>
                    <li>Test webhook receiving (separate step)</li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        // Format phone number as user types
        document.getElementById('to_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.substring(0, 10);
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            }
            e.target.value = value;
        });

        // Character counter
        document.getElementById('message_body').addEventListener('input', function(e) {
            const count = e.target.value.length;
            document.getElementById('charCount').textContent = count;
            
            const charCountElement = document.getElementById('charCount');
            if (count > 160) {
                charCountElement.style.color = '#dc3545';
            } else if (count > 140) {
                charCountElement.style.color = '#ffc107';
            } else {
                charCountElement.style.color = '#28a745';
            }
        });

        // Quick number buttons
        function setNumber(number) {
            const formatted = number.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            document.getElementById('to_number').value = formatted;
        }

        // Quick message buttons
        function setMessage(type) {
            const messages = {
                'test': 'Test message from BulkVS Portal - ' + new Date().toLocaleString(),
                'hello': 'Hello! This is a message from BulkVS Portal. Hope you\'re having a great day! 😊',
                'time': 'Current time: ' + new Date().toLocaleString() + ' - Message sent via BulkVS API ⏰'
            };
            
            document.getElementById('message_body').value = messages[type] || messages['test'];
            
            // Update character count
            const event = new Event('input');
            document.getElementById('message_body').dispatchEvent(event);
        }

        // Initialize character count on page load
        document.addEventListener('DOMContentLoaded', function() {
            const messageBody = document.getElementById('message_body');
            if (messageBody.value) {
                const event = new Event('input');
                messageBody.dispatchEvent(event);
            }
        });
    </script>
</body>
</html>