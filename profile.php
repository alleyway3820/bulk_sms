<?php
// profile.php
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

checkAuth();

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$current_user = getCurrentUser();

$success = '';
$error = '';

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_profile':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if ($username && $email) {
                $update_user = new User($db);
                $update_user->id = $current_user['id'];
                $update_user->username = $username;
                $update_user->email = $email;
                $update_user->role = $current_user['role']; // Keep existing role
                $update_user->is_active = true; // Keep active
                
                if ($update_user->update()) {
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $current_user['username'] = $username;
                    $current_user['email'] = $email;
                    $success = 'Profile updated successfully!';
                    logActivity($current_user['id'], 'profile_updated', 'Profile information updated');
                } else {
                    $error = 'Failed to update profile. Username or email may already be taken.';
                }
            } else {
                $error = 'Please fill in all required fields.';
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($current_password && $new_password && $confirm_password) {
                if ($new_password === $confirm_password) {
                    // Verify current password
                    $verify_user = $user->authenticate($current_user['username'], $current_password);
                    if ($verify_user) {
                        $query = "UPDATE users SET password_hash = :password_hash WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt->bindParam(':password_hash', $new_hash);
                        $stmt->bindParam(':id', $current_user['id']);
                        
                        if ($stmt->execute()) {
                            $success = 'Password changed successfully!';
                            logActivity($current_user['id'], 'password_changed', 'Password updated');
                        } else {
                            $error = 'Failed to update password.';
                        }
                    } else {
                        $error = 'Current password is incorrect.';
                    }
                } else {
                    $error = 'New passwords do not match.';
                }
            } else {
                $error = 'Please fill in all password fields.';
            }
            break;
    }
}

// Get user data
$user_data = $user->getById($current_user['id']);

// Get user activity log
$activity_query = "SELECT * FROM activity_log WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20";
$activity_stmt = $db->prepare($activity_query);
$activity_stmt->bindParam(':user_id', $current_user['id']);
$activity_stmt->execute();
$activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - BulkVS Portal</title>
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

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

        .profile-info {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2em;
            font-weight: bold;
            margin-right: 20px;
        }

        .profile-details h3 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .profile-details p {
            margin: 0;
            color: #666;
        }

        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-action {
            font-weight: 600;
            color: #333;
        }

        .activity-details {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .activity-time {
            color: #999;
            font-size: 0.8em;
            float: right;
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
            <a href="profile.php" class="nav-item active">👤 Profile</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>👤 Profile</h1>
            <p>Manage your account settings and view activity</p>
        </div>

        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Profile Information -->
            <div class="content-card">
                <div class="card-header">👤 Profile Information</div>
                <div class="card-body">
                    <div class="profile-info">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                        </div>
                        <div class="profile-details">
                            <h3><?php echo htmlspecialchars($current_user['username']); ?></h3>
                            <p><?php echo htmlspecialchars($current_user['email']); ?></p>
                            <p><strong>Role:</strong> <?php echo ucfirst($current_user['role']); ?></p>
                            <p><strong>Member since:</strong> <?php echo date('M j, Y', strtotime($user_data['created_at'])); ?></p>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($current_user['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                        </div>
                        
                        <button type="submit" class="btn">Update Profile</button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="content-card">
                <div class="card-header">🔒 Change Password</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password:</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password:</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn">Change Password</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="content-card" style="margin-top: 30px;">
            <div class="card-header">📋 Recent Activity</div>
            <div class="card-body">
                <?php if (!empty($activities)): ?>
                    <div class="activity-list">
                        <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-action">
                                <?php 
                                $action_labels = [
                                    'login' => 'Logged in',
                                    'message_sent' => 'Sent message',
                                    'message_received' => 'Received message',
                                    'profile_updated' => 'Updated profile',
                                    'password_changed' => 'Changed password'
                                ];
                                echo $action_labels[$activity['action']] ?? ucfirst(str_replace('_', ' ', $activity['action']));
                                ?>
                                <span class="activity-time"><?php echo timeAgo($activity['created_at']); ?></span>
                            </div>
                            <?php if ($activity['details']): ?>
                                <div class="activity-details"><?php echo htmlspecialchars($activity['details']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <div style="font-size: 3em; margin-bottom: 15px; opacity: 0.3;">📋</div>
                        <p>No activity logged yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strength = calculatePasswordStrength(password);
            
            // Remove existing indicator
            const existingIndicator = document.querySelector('.password-strength');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            // Add strength indicator
            const indicator = document.createElement('div');
            indicator.className = 'password-strength';
            indicator.style.marginTop = '5px';
            indicator.style.fontSize = '0.9em';
            
            if (password.length === 0) {
                return;
            } else if (strength < 3) {
                indicator.style.color = '#dc3545';
                indicator.textContent = 'Weak password';
            } else if (strength < 4) {
                indicator.style.color = '#ffc107';
                indicator.textContent = 'Medium password';
            } else {
                indicator.style.color = '#28a745';
                indicator.textContent = 'Strong password';
            }
            
            e.target.parentNode.appendChild(indicator);
        });

        function calculatePasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            return strength;
        }

        // Confirm password match
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = e.target.value;
            
            // Remove existing indicator
            const existingIndicator = document.querySelector('.password-match');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            if (confirmPassword.length === 0) {
                return;
            }
            
            // Add match indicator
            const indicator = document.createElement('div');
            indicator.className = 'password-match';
            indicator.style.marginTop = '5px';
            indicator.style.fontSize = '0.9em';
            
            if (newPassword === confirmPassword) {
                indicator.style.color = '#28a745';
                indicator.textContent = 'Passwords match';
            } else {
                indicator.style.color = '#dc3545';
                indicator.textContent = 'Passwords do not match';
            }
            
            e.target.parentNode.appendChild(indicator);
        });
    </script>
</body>
</html>