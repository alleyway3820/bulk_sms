<?php
// api/get-user-permissions.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/UserPhonePermission.php';
require_once '../includes/session.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();
$permission = new UserPhonePermission($db);

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

try {
    $permissions = $permission->getUserPermissions($user_id);
    
    echo json_encode([
        'success' => true,
        'permissions' => $permissions
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load permissions'
    ]);
}
?>
