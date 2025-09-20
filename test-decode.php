<?php
// test-decode.php - Test the message decoding function
?>
<!DOCTYPE html>
<html>
<head>
    <title>Message Decode Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-case { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        .result { background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .code { font-family: monospace; background: #e9ecef; padding: 10px; border-radius: 5px; }
        input, button { padding: 10px; margin: 5px; font-size: 16px; }
        button { background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Message Decode Test</h1>
        
        <?php
        // The same decoding function from the webhook
        function decodeMessage($message) {
            if (empty($message)) {
                return $message;
            }
            
            $steps = [];
            $steps[] = "Original: " . $message;
            
            // Step 1: URL decode
            $decoded = urldecode($message);
            $steps[] = "After urldecode(): " . $decoded;
            
            // Step 2: Check if it needs another round of decoding
            if ($decoded !== $message && strpos($decoded, '%') !== false) {
                $decoded = urldecode($decoded);
                $steps[] = "After double decode: " . $decoded;
            }
            
            // Step 3: Clean up any extra spaces
            $decoded = trim($decoded);
            $steps[] = "Final result: " . $decoded;
            
            return ['result' => $decoded, 'steps' => $steps];
        }
        
        // Test cases
        $test_cases = [
            'Where+are+you+%3F%F0%9F%A4%A4',
            'Hello+World%21',
            'Test%20with%20spaces',
            'Normal message without encoding',
            'Multiple%20%20%20spaces',
            'Special%20chars%3A%20%40%23%24%25',
            'Emoji%20test%3A%20%F0%9F%98%80%F0%9F%91%8B'
        ];
        
        // Handle form submission
        if ($_POST && isset($_POST['test_message'])) {
            $custom_message = $_POST['test_message'];
            echo "<h3>🧪 Custom Test Result:</h3>";
            $result = decodeMessage($custom_message);
            echo "<div class='test-case'>";
            echo "<div class='code'>Input: " . htmlspecialchars($custom_message) . "</div>";
            foreach ($result['steps'] as $step) {
                echo "<div style='margin: 5px 0;'>" . htmlspecialchars($step) . "</div>";
            }
            echo "<div class='result'><strong>Final: " . htmlspecialchars($result['result']) . "</strong></div>";
            echo "</div>";
        }
        ?>
        
        <h3>📝 Test Your Own Message:</h3>
        <form method="POST">
            <input type="text" name="test_message" placeholder="Enter encoded message..." style="width: 60%;" value="<?php echo htmlspecialchars($_POST['test_message'] ?? ''); ?>">
            <button type="submit">Decode</button>
        </form>
        
        <h3>🧪 Common Test Cases:</h3>
        
        <?php foreach ($test_cases as $test): ?>
            <?php $result = decodeMessage($test); ?>
            <div class="test-case">
                <div class="code">Input: <?php echo htmlspecialchars($test); ?></div>
                <?php foreach ($result['steps'] as $step): ?>
                    <div style="margin: 5px 0;"><?php echo htmlspecialchars($step); ?></div>
                <?php endforeach; ?>
                <div class="result"><strong>Final: <?php echo htmlspecialchars($result['result']); ?></strong></div>
            </div>
        <?php endforeach; ?>
        
        <h3>🔧 How to Fix Your Webhook:</h3>
        <ol>
            <li><strong>Replace your webhook.php</strong> with the <code>webhook-decode-fix.php</code> file</li>
            <li><strong>Test with a real message</strong> to see the decoding in action</li>
            <li><strong>Check the logs</strong> at <code>logs/webhook.log</code> to see the decoding steps</li>
        </ol>
        
        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 20px;">
            <strong>💡 The Fix:</strong><br>
            The webhook now includes a <code>decodeMessage()</code> function that:
            <ul>
                <li>✅ URL decodes messages using <code>urldecode()</code></li>
                <li>✅ Handles double encoding (if needed)</li>
                <li>✅ Cleans up extra spaces</li>
                <li>✅ Logs each step for debugging</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="webhook-tester.php" style="color: #007bff;">→ Test Your Fixed Webhook</a>
        </div>
    </div>
</body>
</html>