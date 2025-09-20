<?php
// test-sms.php - Simple SMS API Test Page
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

// **HARDCODED CONFIG - UPDATE THESE VALUES**
$BULKVS_API_USERNAME = 'tonyc';     // Replace with your BulkVS username
$BULKVS_API_PASSWORD = 'a93ed4a2a91687c51dd0d452e5cc8546';     // Replace with your BulkVS password
$FROM_NUMBER = '18324786722';                   // Replace with your BulkVS phone number

// Handle form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'send_test_sms') {
    $to_number = preg_replace('/\D/', '', $_POST['to_number'] ?? '');
    $message_body = trim($_POST['message_body'] ?? '');
    
    if ($to_number && $message_body) {
        try {
            // Send SMS via BulkVS API
            $credentials = base64_encode($BULKVS_API_USERNAME . ':' . $BULKVS_API_PASSWORD);
            
            $data = [
                'to' => $to_number,
                'from' => $FROM_NUMBER,
                'message' => $message_body,
                'method' => 'post'
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/messageSend",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . $credentials
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error_info = curl_error($curl);
            curl_close($curl);

            $api_response = "HTTP Code: $httpCode\n";
            $api_response .= "Response: $response\n";
            if ($error_info) {
                $api_response .= "CURL Error: $error_info\n";
            }

            if ($httpCode == 200) {
                $success = 'SMS sent successfully!';
                
                // Optionally save to database
                try {
                    $pdo = new PDO("mysql:host=localhost;dbname=sms_sms", 'sms_sms', 'YOUR_ACTUAL_DB_PASSWORD');
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    $insert_query = "INSERT INTO messages (from_number, to_number, message_body, direction, status, bulkvs_message_id, user_id, created_at) 
                                     VALUES (?, ?, ?, 'outbound', 'sent', ?, ?, NOW())";
                    $insert_stmt = $pdo->prepare($insert_query);
                    $insert_stmt->execute([$FROM_NUMBER, $to_number, $message_body, $response, $current_user['id']]);
                } catch (Exception $e) {
                    // Database save failed, but SMS was sent
                    $api_response .= "\nNote: SMS sent but failed to save to database: " . $e->getMessage();
                }
            } else {
                $error = 'Failed to send SMS. Check the API response below.';
            }
            
        } catch (Exception $e) {
            $error = 'Error sending SMS: ' . $e->getMessage();
            $api_response = $e->getMessage();
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

// Test API connection
$connection_status = '';
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'test_connection') {
    try {
        $credentials = base64_encode($BULKVS_API_USERNAME . ':' . $BULKVS_API_PASSWORD);
        
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
        $error_info = curl_error($curl);
        curl_close($curl);

        $connection_status = "HTTP Code: $httpCode\n";
        $connection_status .= "Response: $response\n";
        if ($error_info) {
            $connection_status .= "CURL Error: $error_info\n";
        }

        if ($httpCode === 200) {
            $success = 'API connection successful!';
        } elseif ($httpCode === 401) {
            $error = 'Invalid API credentials';
        } else {
            $error = "API connection failed with HTTP $httpCode";
        }
        
    } catch (Exception $e) {
        $error = 'Connection test failed: ' . $e->getMessage();
        $connection_status = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS API Test - BulkVS Portal</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            margin: 0;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .nav-item {
            display: block;
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            transition: all 0.3s ease;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .page-header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
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

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
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
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .config-info {
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .config-info h4 {
            margin: 0 0 15px 0;
            color: #333;
        }

        .config-info code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        .api-response {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }

        .char-count {
            text-align: right;
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>📱 BulkVS</h2>
            <p><?php echo htmlspecialchars($current_user['username']); ?></p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">📊 Dashboard</a>
            <a href="messages.php" class="nav-item">💬 Messages</a>
            <a href="phone-numbers.php" class="nav-item">📞 Phone Numbers</a>
            <?php if ($current_user['role'] === 'admin'): ?>
            <a href="users.php" class="nav-item">👥 User Management</a>
            <a href="settings.php" class="nav-item">⚙️ API Settings</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-item">👤 Profile</a>
            <a href="test-sms.php" class="nav-item active">🧪 SMS Test</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>🧪 SMS API Test</h1>
            <p>Test sending SMS messages via BulkVS API</p>
        </div>

        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- API Configuration -->
            <div class="content-card">
                <div class="card-header">⚙️ API Configuration</div>
                <div class="card-body">
                    <div class="config-info">
                        <h4>Current Settings</h4>
                        <p><strong>API Username:</strong> <code><?php echo htmlspecialchars($BULKVS_API_USERNAME); ?></code></p>
                        <p><strong>From Number:</strong> <code><?php echo htmlspecialchars($FROM_NUMBER); ?></code></p>
                        <p><strong>API Endpoint:</strong> <code>https://portal.bulkvs.com/api/v1.0/messageSend</code></p>
                    </div>
                    
                    <p><strong>To update these settings:</strong></p>
                    <ol>
                        <li>Edit <code>test-sms.php</code></li>
                        <li>Update the hardcoded values at the top</li>
                        <li>Save and refresh this page</li>
                    </ol>
                    
                    <form method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="action" value="test_connection">
                        <button type="submit" class="btn btn-secondary">Test API Connection</button>
                    </form>
                    
                    <?php if ($connection_status): ?>
                        <div class="api-response"><?php echo htmlspecialchars($connection_status); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Send SMS Test -->
            <div class="content-card">
                <div class="card-header">📤 Send Test SMS</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="send_test_sms">
                        
                        <div class="form-group">
                            <label for="to_number">To Number:</label>
                            <input type="tel" id="to_number" name="to_number" 
                                   placeholder="+1 (555) 123-4567" required
                                   value="<?php echo htmlspecialchars($_POST['to_number'] ?? ''); ?>">
                            <small style="color: #666;">Enter the phone number to send SMS to</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="message_body">Message:</label>
                            <textarea id="message_body" name="message_body" 
                                      placeholder="Enter your test message here..." required><?php echo htmlspecialchars($_POST['message_body'] ?? ''); ?></textarea>
                            <div class="char-count">
                                <span id="charCount">0</span>/160 characters
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Send Test SMS</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($api_response): ?>
        <!-- API Response -->
        <div class="content-card" style="margin-top: 30px;">
            <div class="card-header">📋 API Response</div>
            <div class="card-body">
                <div class="api-response"><?php echo htmlspecialchars($api_response); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Usage Instructions -->
        <div class="content-card" style="margin-top: 30px;">
            <div class="card-header">📚 Usage Instructions</div>
            <div class="card-body">
                <h4>How to use this test page:</h4>
                <ol>
                    <li><strong>Update Configuration:</strong> Edit the hardcoded values at the top of <code>test-sms.php</code></li>
                    <li><strong>Test Connection:</strong> Click "Test API Connection" to verify your credentials</li>
                    <li><strong>Send Test SMS:</strong> Enter a phone number and message, then click "Send Test SMS"</li>
                    <li><strong>Check Response:</strong> Review the API response for success/error details</li>
                </ol>
                
                <h4>Required BulkVS Configuration:</h4>
                <ul>
                    <li>Valid BulkVS account with API access</li>
                    <li>API username and password</li>
                    <li>At least one phone number provisioned in your account</li>
                    <li>Sufficient account balance for SMS sending</li>
                </ul>
                
                <h4>Troubleshooting:</h4>
                <ul>
                    <li><strong>HTTP 401:</strong> Invalid API credentials</li>
                    <li><strong>HTTP 400:</strong> Invalid request format or missing data</li>
                    <li><strong>HTTP 403:</strong> Insufficient permissions or account suspended</li>
                    <li><strong>Connection timeout:</strong> Network connectivity issues</li>
                </ul>
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

        // Character counter for message
        document.getElementById('message_body').addEventListener('input', function(e) {
            const count = e.target.value.length;
            document.getElementById('charCount').textContent = count;
            
            const charCountElement = document.getElementById('charCount');
            if (count > 160) {
                charCountElement.style.color = '#dc3545';
                charCountElement.parentElement.style.color = '#dc3545';
            } else if (count > 140) {
                charCountElement.style.color = '#ffc107';
                charCountElement.parentElement.style.color = '#ffc107';
            } else {
                charCountElement.style.color = '#28a745';
                charCountElement.parentElement.style.color = '#666';
            }
        });

        // Auto-focus on page load
        document.addEventListener('DOMContentLoaded', function() {
            const toNumberField = document.getElementById('to_number');
            if (toNumberField && !toNumberField.value) {
                toNumberField.focus();
            }
        });
    </script>
</body>
</html>