<?php
// messages.php
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/PhoneNumber.php';
require_once 'classes/Message.php';
require_once 'classes/ApiSettings.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

checkAuth();

$database = new Database();
$db = $database->getConnection();
$phone = new PhoneNumber($db);
$message = new Message($db);
$api_settings = new ApiSettings($db);
$current_user = getCurrentUser();

$success = '';
$error = '';

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'send_message':
            $from_number = $_POST['from_number'] ?? '';
            $to_number = preg_replace('/\D/', '', $_POST['to_number'] ?? '');
            $message_body = trim($_POST['message_body'] ?? '');
            
            if ($from_number && $to_number && $message_body) {
                // Check if user has permission to send from this number
                $permission_check = "SELECT COUNT(*) as count FROM user_phone_permissions upp 
                                   JOIN phone_numbers pn ON upp.phone_number_id = pn.id 
                                   WHERE upp.user_id = :user_id AND pn.number = :from_number AND upp.can_send = 1";
                $perm_stmt = $db->prepare($permission_check);
                $perm_stmt->bindParam(':user_id', $current_user['id']);
                $perm_stmt->bindParam(':from_number', $from_number);
                $perm_stmt->execute();
                $has_permission = $perm_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                
                if ($has_permission) {
                    $new_message = new Message($db);
                    $new_message->from_number = $from_number;
                    $new_message->to_number = $to_number;
                    $new_message->message_body = $message_body;
                    $new_message->direction = 'outbound';
                    $new_message->status = 'pending';
                    $new_message->user_id = $current_user['id'];
                    
                    if ($new_message->create()) {
                        // Get API settings and send SMS
                        $api_config = $api_settings->get();
                        if ($api_config && $new_message->sendSMS($api_config['api_username'], $api_config['api_password'])) {
                            $success = 'Message sent successfully!';
                            logActivity($current_user['id'], 'message_sent', "To: $to_number");
                        } else {
                            $error = 'Failed to send message. Please check API settings.';
                        }
                    } else {
                        $error = 'Failed to save message.';
                    }
                } else {
                    $error = 'You do not have permission to send from this number.';
                }
            } else {
                $error = 'Please fill in all fields.';
            }
            break;
    }
}

// Get user's phone numbers with send permission
$send_numbers = $phone->getUserNumbers($current_user['id']);
$send_numbers = array_filter($send_numbers, function($num) {
    return $num['can_send'];
});

// Get selected conversation
$selected_phone = $_GET['phone'] ?? '';
$selected_contact = $_GET['contact'] ?? '';
$conversations = [];
$messages = [];

if ($selected_phone) {
    $message_obj = new Message($db);
    $conversations = $message_obj->getConversations($current_user['id'], $selected_phone);
    
    if ($selected_contact) {
        $messages = $message_obj->getUserMessages($current_user['id'], $selected_phone);
        $messages = array_filter($messages, function($msg) use ($selected_contact, $selected_phone) {
            return ($msg['from_number'] == $selected_contact && $msg['to_number'] == $selected_phone) ||
                   ($msg['to_number'] == $selected_contact && $msg['from_number'] == $selected_phone);
        });
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - BulkVS Portal</title>
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
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .page-header {
            background: white;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #e9ecef;
        }

        .messages-container {
            flex: 1;
            display: grid;
            grid-template-columns: 300px 300px 1fr;
            height: calc(100vh - 100px);
        }

        .phone-list {
            background: white;
            border-right: 1px solid #e9ecef;
            overflow-y: auto;
        }

        .conversation-list {
            background: #f8f9fa;
            border-right: 1px solid #e9ecef;
            overflow-y: auto;
        }

        .chat-area {
            display: flex;
            flex-direction: column;
            background: white;
        }

        .list-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            background: #f8f9fa;
        }

        .phone-item, .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .phone-item:hover, .conversation-item:hover {
            background: #f8f9fa;
        }

        .phone-item.active, .conversation-item.active {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
        }

        .phone-number {
            font-weight: 600;
            color: #333;
        }

        .phone-name {
            color: #666;
            font-size: 0.9em;
        }

        .contact-number {
            font-weight: 600;
            color: #333;
        }

        .last-message {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .message-time {
            color: #999;
            font-size: 0.8em;
            float: right;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .message-bubble {
            max-width: 70%;
            margin-bottom: 15px;
            padding: 12px 16px;
            border-radius: 20px;
            word-wrap: break-word;
        }

        .message-bubble.inbound {
            background: #e3f2fd;
            margin-right: auto;
            border-bottom-left-radius: 5px;
        }

        .message-bubble.outbound {
            background: #4caf50;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }

        .message-meta {
            font-size: 0.8em;
            opacity: 0.7;
            margin-top: 5px;
        }

        .chat-input {
            padding: 20px;
            border-top: 1px solid #e9ecef;
            background: white;
        }

        .compose-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 60px;
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
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
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
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
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

        .compose-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
        }

        .modal-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 20px;
            font-size: 1.2em;
            font-weight: 600;
            border-radius: 15px 15px 0 0;
        }

        .modal-body {
            padding: 25px;
        }

        .close {
            color: white;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            opacity: 0.7;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .messages-container {
                grid-template-columns: 1fr;
            }
            
            .phone-list, .conversation-list {
                display: none;
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
            <a href="messages.php" class="nav-item active">💬 Messages</a>
            <a href="phone-numbers.php" class="nav-item">📞 Phone Numbers</a>
            <?php if ($current_user['role'] === 'admin'): ?>
            <a href="users.php" class="nav-item">👥 User Management</a>
            <a href="settings.php" class="nav-item">⚙️ API Settings</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-item">👤 Profile</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>💬 Messages</h1>
            <button class="btn" style="float: right;" onclick="openComposeModal()">+ New Message</button>
            
            <?php if ($success): ?>
                <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </div>

        <div class="messages-container">
            <!-- Phone Numbers List -->
            <div class="phone-list">
                <div class="list-header">Your Phone Numbers</div>
                <?php if (!empty($send_numbers)): ?>
                    <?php foreach ($send_numbers as $phone_num): ?>
                    <div class="phone-item <?php echo $selected_phone === $phone_num['number'] ? 'active' : ''; ?>" 
                         onclick="selectPhone('<?php echo $phone_num['number']; ?>')">
                        <div class="phone-number"><?php echo formatPhoneNumber($phone_num['number']); ?></div>
                        <div class="phone-name"><?php echo htmlspecialchars($phone_num['friendly_name'] ?? 'No name set'); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">📞</div>
                        <p>No phone numbers available</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Conversations List -->
            <div class="conversation-list">
                <div class="list-header">Conversations</div>
                <?php if ($selected_phone && !empty($conversations)): ?>
                    <?php foreach ($conversations as $conv): ?>
                    <div class="conversation-item <?php echo $selected_contact === $conv['contact_number'] ? 'active' : ''; ?>" 
                         onclick="selectConversation('<?php echo $selected_phone; ?>', '<?php echo $conv['contact_number']; ?>')">
                        <div class="contact-number"><?php echo formatPhoneNumber($conv['contact_number']); ?></div>
                        <div class="message-time"><?php echo timeAgo($conv['last_message_time']); ?></div>
                        <div class="last-message"><?php echo $conv['message_count']; ?> messages</div>
                    </div>
                    <?php endforeach; ?>
                <?php elseif ($selected_phone): ?>
                    <div class="empty-state">
                        <div class="icon">💬</div>
                        <p>No conversations yet</p>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">👈</div>
                        <p>Select a phone number</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Chat Area -->
            <div class="chat-area">
                <?php if ($selected_phone && $selected_contact): ?>
                    <div class="chat-header">
                        <strong><?php echo formatPhoneNumber($selected_contact); ?></strong>
                        <span style="color: #666; margin-left: 10px;">
                            via <?php echo formatPhoneNumber($selected_phone); ?>
                        </span>
                    </div>
                    
                    <div class="chat-messages" id="chatMessages">
                        <?php if (!empty($messages)): ?>
                            <?php foreach ($messages as $msg): ?>
                            <div class="message-bubble <?php echo $msg['direction']; ?>">
                                <?php echo nl2br(htmlspecialchars($msg['message_body'])); ?>
                                <div class="message-meta">
                                    <?php echo timeAgo($msg['created_at']); ?>
                                    <?php if ($msg['direction'] === 'outbound'): ?>
                                        • <?php echo ucfirst($msg['status']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="icon">💬</div>
                                <p>No messages in this conversation</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="chat-input">
                        <form method="POST" class="compose-form">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="from_number" value="<?php echo htmlspecialchars($selected_phone); ?>">
                            <input type="hidden" name="to_number" value="<?php echo htmlspecialchars($selected_contact); ?>">
                            
                            <div class="form-group">
                                <textarea name="message_body" placeholder="Type your message..." required></textarea>
                            </div>
                            
                            <button type="submit" class="btn">Send</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                        <div class="icon">💬</div>
                        <h3>Select a conversation to start messaging</h3>
                        <p>Choose a phone number and conversation from the left panels, or start a new message.</p>
                        <button class="btn" onclick="openComposeModal()">Start New Conversation</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Compose Modal -->
    <div id="composeModal" class="compose-modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeComposeModal()">&times;</span>
                New Message
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="send_message">
                    
                    <div class="form-group">
                        <label for="from_number">From Number:</label>
                        <select id="from_number" name="from_number" required>
                            <option value="">Select a phone number</option>
                            <?php foreach ($send_numbers as $phone_num): ?>
                            <option value="<?php echo htmlspecialchars($phone_num['number']); ?>">
                                <?php echo formatPhoneNumber($phone_num['number']); ?>
                                <?php if ($phone_num['friendly_name']): ?>
                                    (<?php echo htmlspecialchars($phone_num['friendly_name']); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="to_number">To Number:</label>
                        <input type="tel" id="to_number" name="to_number" placeholder="+1 (555) 123-4567" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message_body">Message:</label>
                        <textarea id="message_body" name="message_body" rows="4" placeholder="Type your message..." required></textarea>
                        <div style="color: #666; font-size: 0.9em; margin-top: 5px;">
                            <span id="charCount">0</span>/160 characters
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Send Message</button>
                    <button type="button" class="btn" style="background: #6c757d; margin-left: 10px;" onclick="closeComposeModal()">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function selectPhone(phoneNumber) {
            window.location.href = `messages.php?phone=${encodeURIComponent(phoneNumber)}`;
        }

        function selectConversation(phoneNumber, contactNumber) {
            window.location.href = `messages.php?phone=${encodeURIComponent(phoneNumber)}&contact=${encodeURIComponent(contactNumber)}`;
        }

        function openComposeModal() {
            document.getElementById('composeModal').style.display = 'block';
        }

        function closeComposeModal() {
            document.getElementById('composeModal').style.display = 'none';
        }

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
            
            if (count > 160) {
                document.getElementById('charCount').style.color = '#dc3545';
            } else {
                document.getElementById('charCount').style.color = '#666';
            }
        });

        // Auto-scroll chat messages to bottom
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('composeModal');
            if (event.target === modal) {
                closeComposeModal();
            }
        }

        // Auto-refresh messages every 30 seconds
        setInterval(function() {
            if (window.location.search.includes('phone=') && window.location.search.includes('contact=')) {
                // Only refresh if we're viewing a conversation
                window.location.reload();
            }
        }, 30000);
    </script>
</body>
</html>