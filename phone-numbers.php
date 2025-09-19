<?php
// phone-numbers.php
require_once 'config/database.php';
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

// Get user's phone numbers with permissions
$user_numbers = $phone->getUserNumbers($current_user['id']);

// Get message statistics for each number
$stats = [];
foreach ($user_numbers as $number) {
    $stats_query = "SELECT 
                        COUNT(*) as total_messages,
                        SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound_count,
                        SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_count,
                        MAX(created_at) as last_activity
                    FROM messages 
                    WHERE to_number = :number OR from_number = :number";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bindParam(':number', $number['number']);
    $stats_stmt->execute();
    $stats[$number['number']] = $stats_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phone Numbers - BulkVS Portal</title>
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

        .numbers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .number-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .number-card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 20px;
            position: relative;
        }

        .card-body {
            padding: 25px;
        }

        .number-display {
            font-size: 1.5em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .number-name {
            opacity: 0.9;
            font-size: 1em;
        }

        .permissions {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .permission-badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
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

        .empty-state {
            text-align: center;
            color: #666;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-state .icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .last-activity {
            color: #666;
            font-size: 0.9em;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .numbers-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="phone-numbers.php" class="nav-item active">📞 Phone Numbers</a>
            <?php if ($current_user['role'] === 'admin'): ?>
            <a href="users.php" class="nav-item">👥 User Management</a>
            <a href="settings.php" class="nav-item">⚙️ API Settings</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-item">👤 Profile</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>📞 Your Phone Numbers</h1>
            <p>Manage and monitor your assigned phone numbers</p>
        </div>

        <?php if (!empty($user_numbers)): ?>
            <div class="numbers-grid">
                <?php foreach ($user_numbers as $number): ?>
                <div class="number-card">
                    <div class="card-header">
                        <div class="number-display"><?php echo formatPhoneNumber($number['number']); ?></div>
                        <div class="number-name"><?php echo htmlspecialchars($number['friendly_name'] ?: 'No name set'); ?></div>
                        
                        <div class="permissions">
                            <?php if ($number['can_send']): ?>
                                <span class="permission-badge">📤 Send</span>
                            <?php endif; ?>
                            <?php if ($number['can_receive']): ?>
                                <span class="permission-badge">📥 Receive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo number_format($stats[$number['number']]['total_messages'] ?? 0); ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo number_format($stats[$number['number']]['inbound_count'] ?? 0); ?></div>
                                <div class="stat-label">Received</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo number_format($stats[$number['number']]['outbound_count'] ?? 0); ?></div>
                                <div class="stat-label">Sent</div>
                            </div>
                        </div>
                        
                        <div class="actions">
                            <?php if ($number['can_send'] || $number['can_receive']): ?>
                                <a href="messages.php?phone=<?php echo urlencode($number['number']); ?>" class="btn">
                                    💬 View Messages
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($number['can_send']): ?>
                                <a href="messages.php?phone=<?php echo urlencode($number['number']); ?>&compose=1" class="btn btn-secondary">
                                    📤 Send Message
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($stats[$number['number']]['last_activity']): ?>
                            <div class="last-activity">
                                <strong>Last Activity:</strong> <?php echo timeAgo($stats[$number['number']]['last_activity']); ?>
                            </div>
                        <?php else: ?>
                            <div class="last-activity">
                                <strong>Status:</strong> No messages yet
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">📞</div>
                <h3>No Phone Numbers Assigned</h3>
                <p>You don't have any phone numbers assigned to your account yet.</p>
                <p>Contact your administrator to get phone number permissions.</p>
                <?php if ($current_user['role'] === 'admin'): ?>
                    <a href="settings.php" class="btn" style="margin-top: 20px;">
                        ⚙️ Manage Phone Numbers
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers for number cards
            const numberCards = document.querySelectorAll('.number-card');
            numberCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on a button
                    if (e.target.closest('.btn')) return;
                    
                    // Get the phone number from the card
                    const numberDisplay = card.querySelector('.number-display');
                    if (numberDisplay) {
                        const phoneNumber = numberDisplay.textContent.replace(/\D/g, '');
                        window.location.href = `messages.php?phone=${encodeURIComponent(phoneNumber)}`;
                    }
                });
                
                // Add cursor pointer to indicate clickability
                card.style.cursor = 'pointer';
            });
            
            // Add tooltips for permission badges
            const permissionBadges = document.querySelectorAll('.permission-badge');
            permissionBadges.forEach(badge => {
                badge.title = badge.textContent.includes('Send') ? 
                    'You can send messages from this number' : 
                    'You can receive messages on this number';
            });
        });
    </script>
</body>
</html>