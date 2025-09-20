<?php
// test_webhook.php - Test your webhook endpoint
?>
<!DOCTYPE html>
<html>
<head>
    <title>Webhook Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .test-section { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .result { background: #e8f5e9; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #ffebee; color: #c62828; }
        .success { background: #e8f5e9; color: #2e7d32; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 5px; overflow-x: auto; }
        button { padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #1976D2; }
    </style>
</head>
<body>
    <h1>🔗 Webhook Test Tool</h1>
    
    <div class="test-section">
        <h2>Test 1: Basic Webhook Response</h2>
        <p>This tests if your webhook endpoint responds to GET requests:</p>
        <button onclick="testWebhookGet()">Test GET Request</button>
        <div id="get-result"></div>
    </div>
    
    <div class="test-section">
        <h2>Test 2: Simulate Incoming SMS</h2>
        <p>This simulates an incoming SMS webhook from BulkVS:</p>
        
        <form onsubmit="testWebhookPost(event)">
            <table>
                <tr>
                    <td>From Number:</td>
                    <td><input type="text" id="from-number" value="15551234567" placeholder="Sender's phone number"></td>
                </tr>
                <tr>
                    <td>To Number:</td>
                    <td><input type="text" id="to-number" value="" placeholder="Your BulkVS number"></td>
                </tr>
                <tr>
                    <td>Message:</td>
                    <td><input type="text" id="message" value="Test webhook message" placeholder="Message content"></td>
                </tr>
                <tr>
                    <td>Message ID:</td>
                    <td><input type="text" id="message-id" value="test_123" placeholder="Unique message ID"></td>
                </tr>
            </table>
            <br>
            <button type="submit">Send Test Webhook</button>
        </form>
        <div id="post-result"></div>
    </div>
    
    <div class="test-section">
        <h2>Test 3: Check Database Setup</h2>
        <button onclick="checkDatabase()">Check Database</button>
        <div id="db-result"></div>
    </div>
    
    <div class="test-section">
        <h2>Test 4: View Recent Webhook Logs</h2>
        <button onclick="viewLogs()">View Logs</button>
        <div id="logs-result"></div>
    </div>

    <script>
        function testWebhookGet() {
            const resultDiv = document.getElementById('get-result');
            resultDiv.innerHTML = '<p>Testing...</p>';
            
            fetch('/webhook.php')
                .then(response => {
                    return response.text().then(text => ({
                        status: response.status,
                        text: text
                    }));
                })
                .then(data => {
                    resultDiv.innerHTML = `
                        <div class="result ${data.status === 200 ? 'success' : 'error'}">
                            <strong>Status:</strong> ${data.status}<br>
                            <strong>Response:</strong> ${data.text}
                        </div>
                    `;
                })
                .catch(error => {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <strong>Error:</strong> ${error.message}
                        </div>
                    `;
                });
        }
        
        function testWebhookPost(event) {
            event.preventDefault();
            
            const resultDiv = document.getElementById('post-result');
            resultDiv.innerHTML = '<p>Testing...</p>';
            
            const data = {
                From: document.getElementById('from-number').value,
                To: document.getElementById('to-number').value,
                Message: document.getElementById('message').value,
                MessageId: document.getElementById('message-id').value
            };
            
            fetch('/webhook.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                return response.text().then(text => ({
                    status: response.status,
                    text: text
                }));
            })
            .then(data => {
                resultDiv.innerHTML = `
                    <div class="result ${data.status === 200 ? 'success' : 'error'}">
                        <strong>Status:</strong> ${data.status}<br>
                        <strong>Response:</strong> ${data.text}<br>
                        <strong>Sent Data:</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="result error">
                        <strong>Error:</strong> ${error.message}
                    </div>
                `;
            });
        }
        
        function checkDatabase() {
            const resultDiv = document.getElementById('db-result');
            resultDiv.innerHTML = '<p>Checking...</p>';
            
            fetch('test_webhook_db.php')
                .then(response => response.text())
                .then(data => {
                    resultDiv.innerHTML = `<div class="result"><pre>${data}</pre></div>`;
                })
                .catch(error => {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <strong>Error:</strong> ${error.message}
                        </div>
                    `;
                });
        }
        
        function viewLogs() {
            const resultDiv = document.getElementById('logs-result');
            resultDiv.innerHTML = '<p>Loading logs...</p>';
            
            fetch('view_webhook_logs.php')
                .then(response => response.text())
                .then(data => {
                    resultDiv.innerHTML = `<div class="result"><pre>${data}</pre></div>`;
                })
                .catch(error => {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <strong>Error:</strong> ${error.message}
                        </div>
                    `;
                });
        }
        
        // Load phone numbers on page load
        window.onload = function() {
            fetch('get_phone_numbers.php')
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        document.getElementById('to-number').value = data[0].number;
                    }
                })
                .catch(error => console.log('Could not load phone numbers'));
        };
    </script>
</body>
</html>