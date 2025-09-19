<?php
// api/test-bulkvs-connection.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/session.php';

checkAdminAuth();

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password required']);
    exit;
}

try {
    $credentials = base64_encode($username . ':' . $password);
    
    // Test API call to BulkVS
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/account",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        throw new Exception('Connection error: ' . $error);
    }

    if ($httpCode === 200) {
        echo json_encode([
            'success' => true,
            'message' => 'API connection successful',
            'account_info' => json_decode($response, true)
        ]);
    } elseif ($httpCode === 401) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid API credentials'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => "API returned HTTP $httpCode"
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
