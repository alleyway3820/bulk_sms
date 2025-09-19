<?php
// includes/functions.php
function formatPhoneNumber($number) {
    $number = preg_replace('/\D/', '', $number);
    if (strlen($number) == 10) {
        return '(' . substr($number, 0, 3) . ') ' . substr($number, 3, 3) . '-' . substr($number, 6);
    } else if (strlen($number) == 11 && $number[0] == '1') {
        return '+1 (' . substr($number, 1, 3) . ') ' . substr($number, 4, 3) . '-' . substr($number, 7);
    }
    return $number;
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Just now';
    } elseif ($time < 3600) {
        return floor($time/60) . ' min ago';
    } elseif ($time < 86400) {
        return floor($time/3600) . ' hours ago';
    } elseif ($time < 2592000) {
        return floor($time/86400) . ' days ago';
    } else {
        return date('M j, Y', strtotime($datetime));
    }
}

function sendRealTimeNotification($user_id, $type, $data) {
    // This would integrate with WebSocket server or Server-Sent Events
    // For now, we'll use a simple database notification system
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO notifications (user_id, type, data, created_at) VALUES (:user_id, :type, :data, NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":type", $type);
    $stmt->bindParam(":data", json_encode($data));
    $stmt->execute();
}

function logActivity($user_id, $action, $details = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO activity_log (user_id, action, details, ip_address, created_at) 
              VALUES (:user_id, :action, :details, :ip_address, NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":action", $action);
    $stmt->bindParam(":details", $details);
    $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
}
?>