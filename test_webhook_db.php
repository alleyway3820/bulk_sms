<?php
// test_webhook_db.php - Check database connectivity for webhook
try {
    require_once 'config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        echo "❌ Database connection failed\n";
        exit;
    }
    
    echo "✅ Database connection successful\n\n";
    
    // Check required tables
    $tables = ['users', 'phone_numbers', 'user_phone_permissions', 'messages'];
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' missing\n";
        }
    }
    
    echo "\n--- Phone Numbers ---\n";
    $stmt = $db->prepare("SELECT * FROM phone_numbers WHERE is_active = 1");
    $stmt->execute();
    $phones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($phones)) {
        echo "⚠️  No active phone numbers configured\n";
    } else {
        foreach ($phones as $phone) {
            echo "📞 {$phone['number']} - {$phone['friendly_name']}\n";
        }
    }
    
    echo "\n--- Active Users ---\n";
    $stmt = $db->prepare("SELECT username, email, role FROM users WHERE is_active = 1");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "👤 {$user['username']} ({$user['role']}) - {$user['email']}\n";
    }
    
    echo "\n--- User Permissions ---\n";
    $stmt = $db->prepare("
        SELECT u.username, pn.number, upp.can_send, upp.can_receive 
        FROM user_phone_permissions upp 
        JOIN users u ON upp.user_id = u.id 
        JOIN phone_numbers pn ON upp.phone_number_id = pn.id 
        WHERE u.is_active = 1 AND pn.is_active = 1
    ");
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($permissions)) {
        echo "⚠️  No user permissions configured\n";
    } else {
        foreach ($permissions as $perm) {
            $send = $perm['can_send'] ? '📤' : '🚫';
            $receive = $perm['can_receive'] ? '📥' : '🚫';
            echo "{$perm['username']} -> {$perm['number']} | Send: $send | Receive: $receive\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>

<?php
// view_webhook_logs.php - View recent webhook logs
$log_file = 'logs/webhook.log';

if (!file_exists($log_file)) {
    echo "No webhook logs found. File: $log_file\n";
    exit;
}

$lines = file($log_file);
$recent_lines = array_slice($lines, -20); // Last 20 lines

echo "=== RECENT WEBHOOK LOGS ===\n\n";

foreach ($recent_lines as $line) {
    $data = json_decode($line, true);
    if ($data) {
        echo "Time: {$data['timestamp']}\n";
        echo "Method: {$data['method']}\n";
        echo "IP: {$data['ip']}\n";
        if (!empty($data['raw_body'])) {
            echo "Body: {$data['raw_body']}\n";
        }
        echo "---\n";
    } else {
        echo $line;
    }
}

if (count($lines) > 20) {
    echo "\n(Showing last 20 entries out of " . count($lines) . " total)\n";
}
?>

<?php
// get_phone_numbers.php - Get phone numbers for testing
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT number, friendly_name FROM phone_numbers WHERE is_active = 1");
    $stmt->execute();
    $phones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($phones);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>