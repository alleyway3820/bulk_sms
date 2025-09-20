<?php
// webhook-tester.php - Test your webhook with proper POST requests like BulkVS sends
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Tester - BulkVS Portal</title>
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
        
        .result {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 14px;
        }
        
        .success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .test-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .test-btn {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 3px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .test-btn:hover {
            background: #545b62;
        }
        
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
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
        <h1>🔧 Webhook Tester</h1>
        
        <div class="info">
            <strong>✅ Your webhook correctly rejected the GET request with a 405 error.</strong><br>
            This is proper behavior! BulkVS sends POST requests, not GET requests.<br>
            Use this tester to send proper POST requests like BulkVS does.
        </div>
        
        <form id="webhookTestForm">
            <div class="form-group">
                <label for="webhook_url">Webhook URL:</label>
                <input type="url" id="webhook_url" name="webhook_url" 
                       value="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/webhook.php'; ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label>Quick Test Scenarios:</label>
                <div class="test-buttons">
                    <button type="button" class="test-btn" onclick="setTestData('simple')">Simple Test</button>
                    <button type="button" class="test-btn" onclick="setTestData('encoded')">URL Encoded Message</button>
                    <button type="button" class="test-btn" onclick="setTestData('emoji')">Emoji Test</button>
                    <button type="button" class="test-btn" onclick="setTestData('complex')">Complex Message</button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="from_number">From Number:</label>
                <input type="tel" id="from_number" name="from_number" value="5551234567" required>
            </div>
            
            <div class="form-group">
                <label for="to_number">To Number (must match your database):</label>
                <input type="tel" id="to_number" name="to_number" value="8324786722" required>
                <small>This should match a phone number in your database with receive permissions</small>
            </div>
            
            <div class="form-group">
                <label for="message_body">Message Body:</label>
                <textarea id="message_body" name="message_body" rows="4" required>Hello! This is a test message.</textarea>
            </div>
            
            <div class="form-group">
                <label for="message_id">Message ID (optional):</label>
                <input type="text" id="message_id" name="message_id" value="">
            </div>
            
            <button type="submit">🚀 Test Webhook (POST)</button>
            <button type="button" onclick="testGetRequest()">❌ Test GET (Should Fail)</button>
        </form>
        
        <div id="result" class="result" style="display: none;"></div>
    </div>

    <script>
        // Test data presets
        const testData = {
            simple: {
                from: '5551234567',
                to: '8324786722',
                message: 'Hello! This is a simple test message.',
                id: 'test-simple-' + Date.now()
            },
            encoded: {
                from: '5551234567',
                to: '8324786722',
                message: 'Where+are+you+%3F%F0%9F%A4%A4',
                id: 'test-encoded-' + Date.now()
            },
            emoji: {
                from: '5551234567',
                to: '8324786722',
                message: 'Hello! 👋 How are you? 😊🎉',
                id: 'test-emoji-' + Date.now()
            },
            complex: {
                from: '5551234567',
                to: '8324786722',
                message: 'Test message with "quotes" & special chars: @#$%^&*()_+ 🚀',
                id: 'test-complex-' + Date.now()
            }
        };

        function setTestData(type) {
            const data = testData[type];
            document.getElementById('from_number').value = data.from;
            document.getElementById('to_number').value = data.to;
            document.getElementById('message_body').value = data.message;
            document.getElementById('message_id').value = data.id;
        }

        function showResult(success, title, content) {
            const resultDiv = document.getElementById('result');
            resultDiv.className = 'result ' + (success ? 'success' : 'error');
            resultDiv.innerHTML = `<strong>${title}</strong><pre>${content}</pre>`;
            resultDiv.style.display = 'block';
            resultDiv.scrollIntoView({ behavior: 'smooth' });
        }

        // Test POST request (correct method)
        document.getElementById('webhookTestForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const webhookUrl = formData.get('webhook_url');
            
            // Build BulkVS-style JSON payload
            const payload = {
                From: formData.get('from_number'),
                To: [formData.get('to_number')],
                Message: formData.get('message_body'),
                MessageId: formData.get('message_id') || 'test-' + Date.now(),
                Timestamp: new Date().toISOString()
            };
            
            try {
                showResult(false, '⏳ Testing...', 'Sending POST request to webhook...');
                
                const response = await fetch(webhookUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const responseText = await response.text();
                let responseJson;
                try {
                    responseJson = JSON.parse(responseText);
                } catch (e) {
                    responseJson = null;
                }
                
                const result = `
HTTP Status: ${response.status} ${response.statusText}

Request Payload:
${JSON.stringify(payload, null, 2)}

Response Headers:
${Array.from(response.headers.entries()).map(([key, value]) => `${key}: ${value}`).join('\n')}

Response Body:
${responseJson ? JSON.stringify(responseJson, null, 2) : responseText}
                `;
                
                const success = response.status >= 200 && response.status < 300;
                showResult(success, 
                    success ? '✅ POST Request Successful!' : '❌ POST Request Failed', 
                    result);
                
            } catch (error) {
                showResult(false, '❌ Request Error', `Error: ${error.message}`);
            }
        });

        // Test GET request (should fail with 405)
        async function testGetRequest() {
            const webhookUrl = document.getElementById('webhook_url').value;
            
            try {
                showResult(false, '⏳ Testing GET...', 'Sending GET request (should fail)...');
                
                const response = await fetch(webhookUrl + '?test=get', {
                    method: 'GET'
                });
                
                const responseText = await response.text();
                
                const result = `
HTTP Status: ${response.status} ${response.statusText}

Response:
${responseText}

Expected: 405 Method Not Allowed (this is correct behavior!)
                `;
                
                const isCorrect405 = response.status === 405;
                showResult(isCorrect405, 
                    isCorrect405 ? '✅ GET Correctly Rejected!' : '⚠️ Unexpected GET Response', 
                    result);
                
            } catch (error) {
                showResult(false, '❌ Request Error', `Error: ${error.message}`);
            }
        }

        // Load simple test data by default
        setTestData('simple');
    </script>
</body>
</html>