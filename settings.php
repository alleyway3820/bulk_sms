<?php
// settings.php - Complete file
require_once 'config/database.php';
require_once 'classes/ApiSettings.php';
require_once 'classes/PhoneNumber.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();
$api_settings = new ApiSettings($db);
$phone = new PhoneNumber($db);
$current_user = getCurrentUser();

$success = '';
$error = '';

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_api':
            $api_username = trim($_POST['api_username'] ?? '');
            $api_password = trim($_POST['api_password'] ?? '');
            $webhook_url = trim($_POST['webhook_url'] ?? '');
            
            if ($api_username && $api_password && $webhook_url) {
                if ($api_settings->update($api_username, $api_password, $webhook_url)) {
                    $success = 'API settings updated successfully!';
                    logActivity($current_user['id'], 'api_settings_updated', 'API credentials updated');
                } else {
                    $error = 'Failed to update API settings.';
                }
            } else {
                $error = 'Please fill in all required fields.';
            }
            break;
            
        case 'add_phone':
            $number = preg_replace('/\D/', '', $_POST['number'] ?? '');
            $friendly_name = trim($_POST['friendly_name'] ?? '');
            
            if ($number) {
                $new_phone = new PhoneNumber($db);
                $new_phone->number = $number;
                $new_phone->friendly_name = $friendly_name;
                $new_phone->is_active = true;
                
                if ($new_phone->create()) {
                    $success = 'Phone number added successfully!';
                    logActivity($current_user['id'], 'phone_number_added', "Number: $number");
                } else {
                    $error = 'Failed to add phone number. It may already exist.';
                }
            } else {
                $error = 'Please enter a valid phone number.';
            }
            break;
            
        case 'update_phone':
            $phone_id = (int)$_POST['phone_id'];
            $number = preg_replace('/\D/', '', $_POST['number'] ?? '');
            $friendly_name = trim($_POST['friendly_name'] ?? '');
            $is_active = isset($_POST['is_active']);
            
            if ($phone_id && $number) {
                $update_phone = new PhoneNumber($db);
                $update_phone->id = $phone_id;
                $update_phone->number = $number;
                $update_phone->friendly_name = $friendly_name;
                $update_phone->is_active = $is_active;
                
                if ($update_phone->update()) {
                    $success = 'Phone number updated successfully!';
                    logActivity($current_user['id'], 'phone_number_updated', "ID: $phone_id");
                } else {
                    $error = 'Failed to update phone number.';
                }
            } else {
                $error = 'Invalid phone number data.';
            }
            break;
            
        case 'delete_phone':
            $phone_id = (int)$_POST['phone_id'];
            if ($phone_id) {
                if ($phone->delete($phone_id)) {
                    $success = 'Phone number deleted successfully!';
                    logActivity($current_user['id'], 'phone_number_deleted', "ID: $phone_id");
                } else {
                    $error = 'Failed to delete phone number.';
                }
            }
            break;
    }
}

// Get current settings
$current_settings = $api_settings->get();
$phone_numbers = $phone->getAll();

// Get selected phone for editing
$selected_phone_id = $_GET['edit_phone'] ?? null;
$selected_phone = null;

if ($selected_phone_id) {
    foreach ($phone_numbers as $p) {
        if ($p['id'] == $selected_phone_id) {
            $selected_phone = $p;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BulkVS Portal</title>
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

        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
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
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
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
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .phone-table {
            width: 100%;
            border-collapse: collapse;
        }

        .phone-table th,
        .phone-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .phone-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .webhook-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .webhook-info h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .webhook-info p {
            color: #424242;
            margin-bottom: 10px;
        }

        .webhook-info code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
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

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
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
            <a href="users.php" class="nav-item">👥 User Management</a>
            <a href="settings.php" class="nav-item active">⚙️ API Settings</a>
            <a href="profile.php" class="nav-item">👤 Profile</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>⚙️ Settings</h1>
            <p>Configure API credentials and manage phone numbers</p>
        </div>

        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- API Settings -->
        <div class="content-card">
            <div class="card-header">
                🔧 BulkVS API Configuration
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_api">
                    
                    <div class="form-group">
                        <label for="api_username">API Username:</label>
                        <input type="text" id="api_username" name="api_username" 
                               value="<?php echo htmlspecialchars($current_settings['api_username'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="api_password">API Password:</label>
                        <input type="password" id="api_password" name="api_password" 
                               value="<?php echo htmlspecialchars($current_settings['api_password'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="webhook_url">Webhook URL:</label>
                        <input type="url" id="webhook_url" name="webhook_url" 
                               value="<?php echo htmlspecialchars($current_settings['webhook_url'] ?? ''); ?>" 
                               placeholder="https://yourdomain.com/webhook.php" required>
                    </div>
                    
                    <button type="submit" class="btn">Update API Settings</button>
                </form>
                
                <div class="webhook-info">
                    <h4>📡 Webhook Configuration</h4>
                    <p>To receive incoming SMS messages, configure your BulkVS webhook:</p>
                    <ol>
                        <li>Log into your <a href="https://portal.bulkvs.com/login.php" target="_blank">BulkVS Portal</a></li>
                        <li>Go to <strong>Messaging → Messaging Webhooks</strong></li>
                        <li>Enter a name for your webhook (e.g., "SMS Portal")</li>
                        <li>Set the Message URL to: <code><?php echo htmlspecialchars($current_settings['webhook_url'] ?? 'Not configured'); ?></code></li>
                        <li>Set the Method to <strong>POST</strong></li>
                        <li>Assign this webhook to your phone numbers under <strong>Inbound → DIDs - Manage</strong></li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Phone Numbers Management -->
        <div class="content-card">
            <div class="card-header">
                📞 Phone Numbers Management
                <button class="btn" style="float: right; background: rgba(255,255,255,0.2);" onclick="toggleAddPhoneForm()">
                    + Add Phone Number
                </button>
            </div>
            <div class="card-body">
                <!-- Add Phone Number Form (Hidden by default) -->
                <div id="addPhoneForm" style="display: none; margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                    <h4>Add New Phone Number</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_phone">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label for="number">Phone Number:</label>
                                <input type="tel" id="number" name="number" placeholder="+1234567890" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="friendly_name">Friendly Name (Optional):</label>
                                <input type="text" id="friendly_name" name="friendly_name" placeholder="Main Line, Support, etc.">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Add Phone Number</button>
                        <button type="button" class="btn" style="background: #6c757d;" onclick="toggleAddPhoneForm()">Cancel</button>
                    </form>
                </div>

                <?php if ($selected_phone): ?>
                <!-- Edit Phone Form -->
                <div style="margin-bottom: 30px; padding: 20px; background: #fff3cd; border-radius: 10px;">
                    <h4>Edit Phone Number</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_phone">
                        <input type="hidden" name="phone_id" value="<?php echo $selected_phone['id']; ?>">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label for="edit_number">Phone Number:</label>
                                <input type="tel" id="edit_number" name="number" 
                                       value="<?php echo htmlspecialchars($selected_phone['number']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_friendly_name">Friendly Name:</label>
                                <input type="text" id="edit_friendly_name" name="friendly_name" 
                                       value="<?php echo htmlspecialchars($selected_phone['friendly_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" <?php echo $selected_phone['is_active'] ? 'checked' : ''; ?>>
                                Phone number is active
                            </label>
                        </div>
                        
                        <button type="submit" class="btn">Update Phone Number</button>
                        <a href="settings.php" class="btn" style="background: #6c757d;">Cancel</a>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Phone Numbers Table -->
                <?php if (!empty($phone_numbers)): ?>
                    <table class="phone-table">
                        <thead>
                            <tr>
                                <th>Phone Number</th>
                                <th>Friendly Name</th>
                                <th>Status</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($phone_numbers as $phone_num): ?>
                            <tr>
                                <td><strong><?php echo formatPhoneNumber($phone_num['number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($phone_num['friendly_name'] ?? 'No name set'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $phone_num['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $phone_num['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($phone_num['created_at'])); ?></td>
                                <td>
                                    <a href="?edit_phone=<?php echo $phone_num['id']; ?>" class="btn" style="padding: 6px 12px; font-size: 0.85em; background: #6c757d;">
                                        Edit
                                    </a>
                                    <button onclick="confirmDeletePhone(<?php echo $phone_num['id']; ?>, '<?php echo formatPhoneNumber($phone_num['number']); ?>')" 
                                            class="btn" style="padding: 6px 12px; font-size: 0.85em; background: #dc3545;">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <div style="font-size: 3em; margin-bottom: 15px; opacity: 0.3;">📞</div>
                        <p>No phone numbers configured</p>
                        <button class="btn" onclick="toggleAddPhoneForm()">Add Your First Phone Number</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Information -->
        <div class="content-card">
            <div class="card-header">
                📊 System Information
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <h4>Database Status</h4>
                        <?php
                        try {
                            $db_check = $database->getConnection();
                            echo '<span class="status-badge active">Connected</span>';
                        } catch (Exception $e) {
                            echo '<span class="status-badge inactive">Connection Failed</span>';
                        }
                        ?>
                    </div>
                    
                    <div>
                        <h4>API Status</h4>
                        <?php
                        if ($current_settings && $current_settings['api_username'] && $current_settings['api_password']) {
                            echo '<span class="status-badge active">Configured</span>';
                        } else {
                            echo '<span class="status-badge inactive">Not Configured</span>';
                        }
                        ?>
                    </div>
                    
                    <div>
                        <h4>Webhook URL</h4>
                        <?php
                        if ($current_settings && $current_settings['webhook_url']) {
                            echo '<span class="status-badge active">Set</span>';
                        } else {
                            echo '<span class="status-badge inactive">Not Set</span>';
                        }
                        ?>
                    </div>
                    
                    <div>
                        <h4>Total Users</h4>
                        <?php
                        $user_count_query = "SELECT COUNT(*) as count FROM users WHERE is_active = 1";
                        $user_count_stmt = $db->prepare($user_count_query);
                        $user_count_stmt->execute();
                        $user_count = $user_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        echo '<strong>' . $user_count . '</strong>';
                        ?>
                    </div>
                    
                    <div>
                        <h4>Total Phone Numbers</h4>
                        <strong><?php echo count($phone_numbers); ?></strong>
                    </div>
                    
                    <div>
                        <h4>Messages Today</h4>
                        <?php
                        $msg_count_query = "SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = CURDATE()";
                        $msg_count_stmt = $db->prepare($msg_count_query);
                        $msg_count_stmt->execute();
                        $msg_count = $msg_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        echo '<strong>' . $msg_count . '</strong>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Phone Confirmation Modal -->
    <div id="deletePhoneModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; margin: 10% auto; padding: 0; border-radius: 15px; max-width: 500px; width: 90%;">
            <div style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 20px; font-size: 1.2em; font-weight: 600; border-radius: 15px 15px 0 0;">
                Confirm Delete Phone Number
            </div>
            <div style="padding: 25px;">
                <p>Are you sure you want to delete phone number <strong id="deletePhoneNumber"></strong>?</p>
                <p style="color: #dc3545;">This will also remove all user permissions for this number.</p>
                
                <form method="POST" id="deletePhoneForm">
                    <input type="hidden" name="action" value="delete_phone">
                    <input type="hidden" name="phone_id" id="delete_phone_id">
                    
                    <button type="submit" class="btn" style="background: #dc3545;">Yes, Delete Phone Number</button>
                    <button type="button" class="btn" style="background: #6c757d;" onclick="closeDeletePhoneModal()">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleAddPhoneForm() {
            const form = document.getElementById('addPhoneForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function confirmDeletePhone(phoneId, phoneNumber) {
            document.getElementById('delete_phone_id').value = phoneId;
            document.getElementById('deletePhoneNumber').textContent = phoneNumber;
            document.getElementById('deletePhoneModal').style.display = 'block';
        }

        function closeDeletePhoneModal() {
            document.getElementById('deletePhoneModal').style.display = 'none';
        }

        // Format phone numbers as user types
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 10) {
                    value = value.substring(0, 10);
                    value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                }
                e.target.value = value;
            });
        });

        // Test API connection
        function testAPIConnection() {
            const username = document.getElementById('api_username').value;
            const password = document.getElementById('api_password').value;
            
            if (!username || !password) {
                alert('Please enter API credentials first.');
                return;
            }
            
            // This would make a test call to BulkVS API
            fetch('api/test-bulkvs-connection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('API connection successful!');
                } else {
                    alert('API connection failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error testing connection: ' + error.message);
            });
        }

        // Add test button to API form
        document.addEventListener('DOMContentLoaded', function() {
            const apiForm = document.querySelector('form input[name="action"][value="update_api"]').closest('form');
            const testBtn = document.createElement('button');
            testBtn.type = 'button';
            testBtn.className = 'btn';
            testBtn.style.background = '#28a745';
            testBtn.textContent = 'Test Connection';
            testBtn.onclick = testAPIConnection;
            
            apiForm.querySelector('button[type="submit"]').parentNode.insertBefore(testBtn, apiForm.querySelector('button[type="submit"]').nextSibling);
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.id === 'deletePhoneModal') {
                closeDeletePhoneModal();
            }
        }
    </script>
</body>
</html>