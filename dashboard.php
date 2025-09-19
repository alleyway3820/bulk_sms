<?php
// dashboard.php
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/PhoneNumber.php';
require_once 'classes/Message.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

checkAuth();

$database = new Database();
$db = $database->getConnection();
$phone = new PhoneNumber($db);
$message = new Message($db);
$current_user = getCurrentUser();

// Get user's phone numbers
$user_numbers = $phone->getUserNumbers($current_user['id']);

// Get recent messages
$recent_messages = $message->getUserMessages($current_user['id'], null, 20);

// Get message statistics
$stats_query = "SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound_count,
                    SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_count,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as today_count
                FROM messages m
                JOIN user_phone_permissions upp ON (m.to_number IN (SELECT number FROM phone_numbers WHERE id = upp.phone_number_id) OR m.from_number IN (SELECT number FROM phone_numbers WHERE id = upp.phone_number_id))
                WHERE upp.user_id = :user_id";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $current_user['id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BulkVS Portal</title>
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

        .sidebar-header h2 {
            font-size: 1.5em;
            margin-bottom: 5px;
            font-weight: 300;
        }

        .sidebar-header p {
            opacity: 0.8;
            font-size: 0.9em;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            display: block;
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: white;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h1 {
            color: #333;
            margin-bottom: 5px;
        }

        .welcome-text p {
            color: #666;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-card .stat-label {
            color: #666;
            font-size: 1.1em;
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
            padding: 20px;
        }

        .phone-list {
            list-style: none;
            padding: 0;
        }

        .phone-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .phone-item:last-child {
            border-bottom: none;
        }

        .phone-info h4 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .phone-info p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }

        .phone-status {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .phone-status.active {
            background: #d4edda;
            color: #155724;
        }

        .phone-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .message-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .message-item {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .message-numbers {
            font-weight: 600;
            color: #333;
        }

        .message-time {
            color: #666;
            font-size: 0.9em;
        }

        .message-body {
            color: #555;
            line-height: 1.4;
        }

        .message-direction {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7em;
            font-weight: 600;
            margin-right: 5px;
        }

        .message-direction.inbound {
            background: #d1ecf1;
            color: #0c5460;
        }

        .message-direction.outbound {
            background: #d4edda;
            color: #155724;
        }

        .empty-state {
            text-align: center;
            color: #666;
            padding: 40px 20px;
        }

        .empty-state .icon {
            font-size: 3em;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
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
            <a href="dashboard.php" class="nav-item active">📊 Dashboard</a>
            <a href="messages.php" class="nav-item">💬 Messages</a>
            <a href="phone-numbers.php" class="nav-item">📞 Phone Numbers</a>
            <?php if ($current_user['role'] === 'admin'): ?>
            <a href="users.php" class="nav-item">👥 User Management</a>
            <a href="settings.php" class="nav-item">⚙️ API Settings</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-item">👤 Profile</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="welcome-text">
                <h1>Welcome back, <?php echo htmlspecialchars($current_user['username']); ?>!</h1>
                <p>Here's what's happening with your SMS messages today.</p>
            </div>
            <div class="user-menu">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_messages'] ?? 0); ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['today_count'] ?? 0); ?></div>
                <div class="stat-label">Today's Messages</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['inbound_count'] ?? 0); ?></div>
                <div class="stat-label">Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['outbound_count'] ?? 0); ?></div>
                <div class="stat-label">Sent</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="content-card">
                <div class="card-header">📞 Your Phone Numbers</div>
                <div class="card-body">
                    <?php if (!empty($user_numbers)): ?>
                        <ul class="phone-list">
                            <?php foreach ($user_numbers as $number): ?>
                            <li class="phone-item">
                                <div class="phone-info">
                                    <h4><?php echo formatPhoneNumber($number['number']); ?></h4>
                                    <p><?php echo htmlspecialchars($number['friendly_name'] ?? 'No name set'); ?></p>
                                </div>
                                <span class="phone-status <?php echo $number['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $number['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">📞</div>
                            <p>No phone numbers assigned to you yet.<br>Contact your administrator to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">💬 Recent Messages</div>
                <div class="card-body">
                    <?php if (!empty($recent_messages)): ?>
                        <div class="message-list">
                            <?php foreach ($recent_messages as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <span class="message-numbers">
                                        <span class="message-direction <?php echo $msg['direction']; ?>">
                                            <?php echo strtoupper($msg['direction']); ?>
                                        </span>
                                        <?php echo formatPhoneNumber($msg['from_number']); ?> → 
                                        <?php echo formatPhoneNumber($msg['to_number']); ?>
                                    </span>
                                    <span class="message-time">
                                        <?php echo timeAgo($msg['created_at']); ?>
                                    </span>
                                </div>
                                <div class="message-body">
                                    <?php echo htmlspecialchars(substr($msg['message_body'], 0, 100)); ?>
                                    <?php echo strlen($msg['message_body']) > 100 ? '...' : ''; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">💬</div>
                            <p>No messages yet.<br>Start sending SMS messages to see them here!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time notifications -->
    <script>
        // Simple notification system - would be enhanced with WebSocket
        function checkForNewMessages() {
            fetch('api/check-notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.new_messages > 0) {
                        showNotification(`You have ${data.new_messages} new message(s)`);
                        // Optionally refresh the page or update the UI
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
        }

        function showNotification(message) {
            // Simple notification - can be enhanced with toast libraries
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('BulkVS Portal', {
                    body: message,
                    icon: '/favicon.ico'
                });
            }
        }

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Check for new messages every 30 seconds
        setInterval(checkForNewMessages, 30000);
    </script>
</body>
</html>