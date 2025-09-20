<?php
// setup-webhook.php - Tool to create/update BulkVS webhook configuration
require_once 'config/database.php';
require_once 'classes/ApiSettings.php';
require_once 'includes/session.php';

// Check if user is admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$current_user = getCurrentUser();
if (!$current_user || $current_user['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

$database = new Database();
$db = $database->getConnection();
$api_settings = new ApiSettings($db);

$success = '';
$error = '';
$webhook_response = '';

// Get current API settings
$settings = $api_settings->get();

// Generate webhook URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$webhook_url = $protocol . $host . dirname($_SERVER['REQUEST_URI']) . '/webhook.php';

// Function to create/update webhook via BulkVS API
function createBulkVSWebhook($username, $password, $webhook_name, $webhook_url, $description = '') {
    $credentials = base64_encode($username . ':' . $password);
    
    $webhook_data = [
        "Webhook" => $webhook_name,
        "Description" => $description ?: "SMS Portal Inbound Messages",
        "Url" => $webhook_url,
        "Dlr" => true,
        "Method" => "POST"
    ];
    
    $json_string = json_encode($webhook_data);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/webhook",
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
        'request_data' => $webhook_data
    ];
}

// Function to test webhook URL
function testWebhookUrl($url) {
    $test_data = [
        'From' => '5551234567',
        'To' => ['1234567890'],
        'Message' => 'Test webhook message',
        'MessageId' => 'test-' . time()
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($test_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false // For testing
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    return [
        'success' => ($httpCode == 200 || $httpCode == 400), // 400 is OK for test (missing auth data)
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $curl_error
    ];
}

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create_webhook':
            if ($settings && $settings['api_username'] && $settings['api_password']) {
                $webhook_name = trim($_POST['webhook_name'] ?? 'SMS-Portal-Webhook');
                $description = trim($_POST['description'] ?? 'SMS Portal Inbound Messages');
                
                $result = createBulkVSWebhook(
                    $settings['api_username'], 
                    $settings['api_password'], 
                    $webhook_name, 
                    $webhook_url, 
                    $description
                );
                
                $webhook_response = "
                <strong>Webhook Creation Results:</strong><br>
                HTTP Code: {$result['http_code']}<br>
                Request Data: <pre>" . json_encode($result['request_data'], JSON_PRETTY_PRINT) . "</pre>
                Response: <pre>" . htmlspecialchars($result['response']) . "</pre>
                ";
                
                if ($result['curl_error']) {
                    $webhook_response .= "CURL Error: " . htmlspecialchars($result['curl_error']) . "<br>";
                }
                
                if ($result['success']) {
                    $success = "Webhook created/updated successfully in BulkVS!";
                } else {
                    $error = "Failed to create webhook. HTTP Code: {$result['http_code']}";
                }
            } else {
                $error = "API credentials not configured. Please set up API settings first.";
            }
            break;
            
        case 'test_webhook':
            $test_result = testWebhookUrl($webhook_url);
            
            $webhook_response = "
            <strong>Webhook Test Results:</strong><br>
            URL: " . htmlspecialchars($webhook_url) . "<br>
            HTTP Code: {$test_result['http_code']}<br>
            Response: <pre>" . htmlspecialchars($test_result['response']) . "</pre>
            ";
            
            if ($test_result['curl_error']) {
                $webhook_response .= "CURL Error: " . htmlspecialchars($test_result['curl_error']) . "<br>";
            }
            
            if ($test_result['success']) {
                $success = "Webhook URL is accessible and responding!";
            } else {
                $error = "Webhook URL test failed. HTTP Code: {$test_result['http_code']}";
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Setup - BulkVS Portal</title>
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
        
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
        
        .response {
            background: #e2e3e5;
            color: #383d41;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px solid #d6d8db;
            font-family: monospace;
            font-size: 12px;
        }
        
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 11px;
        }
        
        .webhook-url {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            word-break: break-all;
            margin: 10px 0;
        }
        
        .steps {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .step {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔗 BulkVS Webhook Setup</h1>
        
        <div class="info">
            <strong>Your Webhook URL:</strong>
            <div class="webhook-url"><?php echo htmlspecialchars($webhook_url); ?></div>
            This URL will receive incoming SMS messages from BulkVS.
        </div>
        
        <?php if (!$settings || !$settings['api_username']): ?>
        <div class="error">
            <strong>⚠️ API Credentials Required:</strong><br>
            Please configure your BulkVS API credentials in the <a href="settings.php">Settings</a> page first.
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="steps">
            <h3>📋 Setup Steps:</h3>
            <div class="step"><strong>1.</strong> Test your webhook URL (below)</div>
            <div class="step"><strong>2.</strong> Create webhook in BulkVS API (below)</div>
            <div class="step"><strong>3.</strong> Assign webhook to your phone numbers in BulkVS portal</div>
            <div class="step"><strong>4.</strong> Test by sending SMS to your number</div>
        </div>
        
        <h3>🧪 Test Webhook URL</h3>
        <p>First, verify that your webhook URL is accessible:</p>
        <form method="POST">
            <input type="hidden" name="action" value="test_webhook">
            <button type="submit">Test Webhook URL</button>
        </form>
        
        <h3>🚀 Create BulkVS Webhook</h3>
        <p>Create or update the webhook configuration in your BulkVS account:</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="create_webhook">
            
            <div class="form-group">
                <label for="webhook_name">Webhook Name:</label>
                <input type="text" id="webhook_name" name="webhook_name" 
                       value="SMS-Portal-Webhook" required>
                <small>Unique name for this webhook in your BulkVS account</small>
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <input type="text" id="description" name="description" 
                       value="SMS Portal Inbound Messages">
                <small>Optional description for the webhook</small>
            </div>
            
            <button type="submit">Create/Update Webhook</button>
        </form>
        
        <?php if ($webhook_response): ?>
            <div class="response"><?php echo $webhook_response; ?></div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h3>📱 Manual Configuration</h3>
            <p>If the API method doesn't work, you can manually configure the webhook in your BulkVS portal:</p>
            <ol>
                <li>Log into your <a href="https://portal.bulkvs.com/login.php" target="_blank">BulkVS Portal</a></li>
                <li>Go to <strong>Messaging → Messaging Webhooks</strong></li>
                <li>Click <strong>Add New Webhook</strong></li>
                <li>Enter webhook details:
                    <ul>
                        <li><strong>Name:</strong> SMS-Portal-Webhook</li>
                        <li><strong>URL:</strong> <code><?php echo htmlspecialchars($webhook_url); ?></code></li>
                        <li><strong>Method:</strong> POST</li>
                        <li><strong>DLR:</strong> Enabled</li>
                    </ul>
                </li>
                <li>Save the webhook</li>
                <li>Go to <strong>Inbound → DIDs - Manage</strong></li>
                <li>Assign the webhook to your phone numbers</li>
            </ol>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h4>🔍 Troubleshooting:</h4>
            <ul>
                <li><strong>Webhook not receiving messages:</strong> Check webhook logs at <code>logs/webhook.log</code></li>
                <li><strong>403/404 errors:</strong> Verify webhook URL is accessible from external IPs</li>
                <li><strong>Phone number not found:</strong> Ensure phone numbers in database match BulkVS format</li>
                <li><strong>No authorized users:</strong> Check user permissions for phone numbers</li>
            </ul>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="dashboard.php" style="color: #007bff; text-decoration: none;">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>