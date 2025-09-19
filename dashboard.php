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

<!DOCTYPE html