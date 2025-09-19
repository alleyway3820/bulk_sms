<?php
// users.php - User Management (Admin Only)
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/PhoneNumber.php';
require_once 'classes/UserPhonePermission.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$phone = new PhoneNumber($db);
$permission = new UserPhonePermission($db);
$current_user = getCurrentUser();

$success = '';
$error = '';

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create_user':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            
            if ($username && $email && $password) {
                $new_user = new User($db);
                $new_user->username = $username;
                $new_user->email = $email;
                $new_user->password_hash = password_hash($password, PASSWORD_DEFAULT);
                $new_user->role = $role;
                $new_user->is_active = true;
                
                if ($new_user->create()) {
                    $success = 'User created successfully!';
                    logActivity($current_user['id'], 'user_created', "User: $username");
                } else {
                    $error = 'Failed to create user. Username or email may already exist.';
                }
            } else {
                $error = 'Please fill in all required fields.';
            }
            break;
            
        case 'update_user':
            $user_id = (int)$_POST['user_id'];
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $is_active = isset($_POST['is_active']);
            
            if ($user_id && $username && $email) {
                $update_user = new User($db);
                $update_user->id = $user_id;
                $update_user->username = $username;
                $update_user->email = $email;
                $update_user->role = $role;
                $update_user->is_active = $is_active;
                
                if ($update_user->update()) {
                    $success = 'User updated successfully!';
                    logActivity($current_user['id'], 'user_updated', "User ID: $user_id");
                } else {
                    $error = 'Failed to update user.';
                }
            } else {
                $error = 'Invalid user data.';
            }
            break;
            
        case 'delete_user':
            $user_id = (int)$_POST['user_id'];
            if ($user_id && $user_id !== $current_user['id']) {
                if ($user->delete($user_id)) {
                    $success = 'User deleted successfully!';
                    logActivity($current_user['id'], 'user_deleted', "User ID: $user_id");
                } else {
                    $error = 'Failed to delete user.';
                }
            } else {
                $error = 'Cannot delete your own account or invalid user.';
            }
            break;
            
        case 'assign_numbers':
            $user_id = (int)$_POST['user_id'];
            $phone_numbers = $_POST['phone_numbers'] ?? [];
            
            if ($user_id) {
                // First, remove all existing permissions for this user
                $remove_query = "DELETE FROM user_phone_permissions WHERE user_id = :user_id";
                $remove_stmt = $db->prepare($remove_query);
                $remove_stmt->bindParam(':user_id', $user_id);
                $remove_stmt->execute();
                
                // Then add new permissions
                foreach ($phone_numbers as $phone_id) {
                    $can_send = isset($_POST['can_send_' . $phone_id]);
                    $can_receive = isset($_POST['can_receive_' . $phone_id]);
                    
                    $permission->assignNumber($user_id, (int)$phone_id, $can_send, $can_receive);
                }
                
                $success = 'Phone number permissions updated successfully!';
                logActivity($current_user['id'], 'permissions_updated', "User ID: $user_id");
            } else {
                $error = 'Invalid user ID.';
            }
            break;
    }
}

// Get all users
$users = $user->getAll();
$phone_numbers = $phone->getAll();

// Get selected user for editing
$selected_user_id = $_GET['edit'] ?? null;
$selected_user = null;
$user_permissions = [];

if ($selected_user_id) {
    $selected_user = $user->getById($selected_user_id);
    $user_permissions = $permission->getUserPermissions($selected_user_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - BulkVS Portal</title>
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

        .page-header h1 {
            margin: 0;
            color: #333;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
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
            margin-bottom: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .users-table tr:hover {
            background: #f8f9fa;
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

        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .role-badge.admin {
            background: #fff3cd;
            color: #856404;
        }

        .role-badge.user {
            background: #d1ecf1;
            color: #0c5460;
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
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .permissions-section {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .permissions-section h4 {
            margin: 0 0 15px 0;
            color: #333;
        }

        .phone-permission {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .phone-permission:last-child {
            border-bottom: none;
        }

        .phone-info {
            flex: 1;
        }

        .phone-info h5 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .phone-info p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }

        .permission-controls {
            display: flex;
            gap: 15px;
        }

        .modal {
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
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 20px;
            font-size: 1.2em;
            font-weight: 600;
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
                padding: 15px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .users-table {
                font-size: 0.9em;
            }
            
            .users-table th,
            .users-table td {
                padding: 8px;
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
            <a href="users.php" class="nav-item active">👥 User Management</a>
            <a href="settings.php" class="nav-item">⚙️ API Settings</a>
            <a href="profile.php" class="nav-item">👤 Profile</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>👥 User Management</h1>
            <p>Manage users and their phone number permissions</p>
        </div>

        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Users List -->
            <div class="content-card" style="grid-column: 1 / -1;">
                <div class="card-header">
                    👥 All Users
                    <button class="btn" style="float: right; background: rgba(255,255,255,0.2);" onclick="openModal('createUserModal')">
                        + Add New User
                    </button>
                </div>
                <div class="card-body">
                    <?php if (!empty($users)): ?>
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <span class="role-badge <?php echo $u['role']; ?>">
                                            <?php echo ucfirst($u['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $u['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <a href="?edit=<?php echo $u['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.85em;">
                                            Edit
                                        </a>
                                        <button onclick="openPermissionsModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" 
                                                class="btn" style="padding: 6px 12px; font-size: 0.85em;">
                                            Permissions
                                        </button>
                                        <?php if ($u['id'] != $current_user['id']): ?>
                                            <button onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" 
                                                    class="btn btn-danger" style="padding: 6px 12px; font-size: 0.85em;">
                                                Delete
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <div style="font-size: 3em; margin-bottom: 15px; opacity: 0.3;">👥</div>
                            <p>No users found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selected_user): ?>
            <!-- Edit User Form -->
            <div class="content-card" style="grid-column: 1 / -1;">
                <div class="card-header">
                    ✏️ Edit User: <?php echo htmlspecialchars($selected_user['username']); ?>
                    <a href="users.php" class="btn btn-secondary" style="float: right; background: rgba(255,255,255,0.2);">
                        Cancel
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?php echo $selected_user['id']; ?>">
                        
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($selected_user['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($selected_user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role:</label>
                            <select id="role" name="role">
                                <option value="user" <?php echo $selected_user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo $selected_user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" 
                                   <?php echo $selected_user['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active">User is active</label>
                        </div>
                        
                        <button type="submit" class="btn">Update User</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('createUserModal')">&times;</span>
                Add New User
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="form-group">
                        <label for="new_username">Username:</label>
                        <input type="text" id="new_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_email">Email:</label>
                        <input type="email" id="new_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Password:</label>
                        <input type="password" id="new_password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_role">Role:</label>
                        <select id="new_role" name="role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Create User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Permissions Modal -->
    <div id="permissionsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('permissionsModal')">&times;</span>
                <span id="permissionsModalTitle">Manage Phone Number Permissions</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="permissionsForm">
                    <input type="hidden" name="action" value="assign_numbers">
                    <input type="hidden" name="user_id" id="permissions_user_id">
                    
                    <div class="permissions-section">
                        <h4>Phone Number Access</h4>
                        <?php foreach ($phone_numbers as $phone): ?>
                        <div class="phone-permission">
                            <div class="phone-info">
                                <h5><?php echo formatPhoneNumber($phone['number']); ?></h5>
                                <p><?php echo htmlspecialchars($phone['friendly_name'] ?? 'No name set'); ?></p>
                            </div>
                            <div class="permission-controls">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="phone_numbers[]" value="<?php echo $phone['id']; ?>" 
                                           id="phone_<?php echo $phone['id']; ?>" onchange="togglePermissions(<?php echo $phone['id']; ?>)">
                                    <label for="phone_<?php echo $phone['id']; ?>">Access</label>
                                </div>
                                <div class="checkbox-group" style="display: none;" id="send_<?php echo $phone['id']; ?>">
                                    <input type="checkbox" name="can_send_<?php echo $phone['id']; ?>" 
                                           id="can_send_<?php echo $phone['id']; ?>">
                                    <label for="can_send_<?php echo $phone['id']; ?>">Send</label>
                                </div>
                                <div class="checkbox-group" style="display: none;" id="receive_<?php echo $phone['id']; ?>">
                                    <input type="checkbox" name="can_receive_<?php echo $phone['id']; ?>" 
                                           id="can_receive_<?php echo $phone['id']; ?>">
                                    <label for="can_receive_<?php echo $phone['id']; ?>">Receive</label>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" class="btn">Update Permissions</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('permissionsModal')">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
                Confirm Delete
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                <p style="color: #dc3545;">This action cannot be undone.</p>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    
                    <button type="submit" class="btn btn-danger">Yes, Delete User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openPermissionsModal(userId, username) {
            document.getElementById('permissions_user_id').value = userId;
            document.getElementById('permissionsModalTitle').textContent = `Manage Permissions for ${username}`;
            
            // Load existing permissions via AJAX
            fetch(`api/get-user-permissions.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    // Reset all checkboxes
                    document.querySelectorAll('input[name="phone_numbers[]"]').forEach(cb => {
                        cb.checked = false;
                        togglePermissions(cb.value.replace(/\D/g, ''));
                    });
                    
                    // Set existing permissions
                    data.permissions.forEach(perm => {
                        const phoneCheckbox = document.querySelector(`input[value="${perm.phone_number_id}"]`);
                        if (phoneCheckbox) {
                            phoneCheckbox.checked = true;
                            togglePermissions(perm.phone_number_id);
                            
                            if (perm.can_send) {
                                document.getElementById(`can_send_${perm.phone_number_id}`).querySelector('input').checked = true;
                            }
                            if (perm.can_receive) {
                                document.getElementById(`can_receive_${perm.phone_number_id}`).querySelector('input').checked = true;
                            }
                        }
                    });
                })
                .catch(error => console.error('Error loading permissions:', error));
            
            openModal('permissionsModal');
        }

        function togglePermissions(phoneId) {
            const mainCheckbox = document.getElementById(`phone_${phoneId}`);
            const sendDiv = document.getElementById(`send_${phoneId}`);
            const receiveDiv = document.getElementById(`receive_${phoneId}`);
            
            if (mainCheckbox.checked) {
                sendDiv.style.display = 'block';
                receiveDiv.style.display = 'block';
                // Default to both permissions
                sendDiv.querySelector('input').checked = true;
                receiveDiv.querySelector('input').checked = true;
            } else {
                sendDiv.style.display = 'none';
                receiveDiv.style.display = 'none';
                sendDiv.querySelector('input').checked = false;
                receiveDiv.querySelector('input').checked = false;
            }
        }

        function confirmDelete(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserName').textContent = username;
            openModal('deleteModal');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>