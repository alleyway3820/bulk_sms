<?php
// classes/Message.php - Updated with message content cleaning
class Message {
    private $conn;
    private $table_name = "messages";

    public $id;
    public $from_number;
    public $to_number;
    public $message_body;
    public $direction;
    public $status;
    public $bulkvs_message_id;
    public $user_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Clean and decode message content
     */
    public static function cleanMessageContent($message) {
        if (empty($message)) {
            return $message;
        }
        
        // Step 1: URL decode the message
        $decoded = urldecode($message);
        
        // Step 2: Handle double encoding (sometimes happens)
        $double_decoded = urldecode($decoded);
        if ($double_decoded !== $decoded) {
            $decoded = $double_decoded;
        }
        
        // Step 3: Convert HTML entities if any
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Step 4: Normalize whitespace
        $decoded = preg_replace('/\s+/', ' ', $decoded);
        $decoded = trim($decoded);
        
        // Step 5: Ensure UTF-8 encoding is correct
        if (!mb_check_encoding($decoded, 'UTF-8')) {
            $decoded = mb_convert_encoding($decoded, 'UTF-8', 'auto');
        }
        
        return $decoded;
    }

    public function create() {
        // Clean the message body before saving
        $this->message_body = self::cleanMessageContent($this->message_body);
        
        $query = "INSERT INTO " . $this->table_name . " 
                  (from_number, to_number, message_body, direction, status, bulkvs_message_id, user_id) 
                  VALUES (:from_number, :to_number, :message_body, :direction, :status, :bulkvs_message_id, :user_id)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":from_number", $this->from_number);
        $stmt->bindParam(":to_number", $this->to_number);
        $stmt->bindParam(":message_body", $this->message_body);
        $stmt->bindParam(":direction", $this->direction);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":bulkvs_message_id", $this->bulkvs_message_id);
        $stmt->bindParam(":user_id", $this->user_id);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getUserMessages($user_id, $phone_number = null, $limit = 50) {
        $query = "SELECT m.*, pn.friendly_name 
                  FROM " . $this->table_name . " m 
                  LEFT JOIN phone_numbers pn ON (m.to_number = pn.number OR m.from_number = pn.number)
                  LEFT JOIN user_phone_permissions upp ON pn.id = upp.phone_number_id 
                  WHERE upp.user_id = :user_id";
        
        if ($phone_number) {
            $query .= " AND (m.to_number = :phone_number OR m.from_number = :phone_number)";
        }
        
        $query .= " ORDER BY m.created_at DESC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        
        if ($phone_number) {
            $stmt->bindParam(":phone_number", $phone_number);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConversations($user_id, $phone_number) {
        $query = "SELECT DISTINCT 
                    CASE 
                        WHEN m.direction = 'inbound' THEN m.from_number
                        ELSE m.to_number 
                    END as contact_number,
                    MAX(m.created_at) as last_message_time,
                    COUNT(*) as message_count
                  FROM " . $this->table_name . " m 
                  WHERE (m.to_number = :phone_number OR m.from_number = :phone_number)
                  GROUP BY contact_number
                  ORDER BY last_message_time DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone_number", $phone_number);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Send SMS using OFFICIAL BulkVS documentation format
     */
    public function sendSMS($api_username, $api_password) {
        // Clean phone numbers - remove all non-digits
        $from_clean = preg_replace('/\D/', '', $this->from_number);
        $to_clean = preg_replace('/\D/', '', $this->to_number);
        
        // Create basic auth credentials
        $credentials = base64_encode($api_username . ':' . $api_password);
        
        // OFFICIAL BulkVS JSON structure from documentation
        $json_data = [
            "From" => $from_clean,           // Capital "F" - must match docs exactly
            "To" => [$to_clean],             // Capital "T" - must be array format
            "Message" => $this->message_body // Capital "M" - exact field name
        ];
        
        $json_string = json_encode($json_data);
        
        // Log the API call for debugging
        error_log("BulkVS API Call - JSON: " . $json_string);
        
        // Initialize cURL with exact headers from documentation
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/messageSend",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,                           // POST method as documented
            CURLOPT_POSTFIELDS => $json_string,             // JSON body
            CURLOPT_HTTPHEADER => [
                'accept: application/json',                  // Exact headers from docs
                'Content-Type: application/json',
                'Authorization: Basic ' . $credentials
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'BulkVS-Portal/1.0'
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);

        // Log the complete API response
        error_log("BulkVS API Response - HTTP: $httpCode, Response: $response, Error: $curl_error");

        if ($httpCode == 200) {
            $this->status = 'sent';
            
            // Parse response to get RefId if available
            $response_data = json_decode($response, true);
            if ($response_data && isset($response_data['RefId'])) {
                $this->bulkvs_message_id = $response_data['RefId'];
            } else {
                $this->bulkvs_message_id = $response; // Fallback to raw response
            }
            
            // Update status in database
            $this->updateStatus();
            
            return true;
        } else {
            $this->status = 'failed';
            
            // Try to parse error response
            $error_data = json_decode($response, true);
            if ($error_data && isset($error_data['Description'])) {
                $error_message = $error_data['Description'];
            } else {
                $error_message = "HTTP $httpCode: $response";
            }
            
            // Store error info
            $this->bulkvs_message_id = "ERROR: $error_message";
            $this->updateStatus();
            
            // Log the failure with details
            error_log("BulkVS SMS Send Failed: HTTP $httpCode, Response: $response, CURL Error: $curl_error");
            
            return false;
        }
    }
    
    /**
     * Update message status in database
     */
    private function updateStatus() {
        if ($this->id) {
            $query = "UPDATE " . $this->table_name . " 
                      SET status = :status, bulkvs_message_id = :bulkvs_message_id, 
                          updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":status", $this->status);
            $stmt->bindParam(":bulkvs_message_id", $this->bulkvs_message_id);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();
        }
    }
    
    /**
     * Get message statistics for dashboard
     */
    public function getStats($user_id) {
        $query = "SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound_count,
                    SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_count,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as today_count,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
                  FROM " . $this->table_name . " m
                  LEFT JOIN user_phone_permissions upp ON (
                      (m.from_number IN (SELECT number FROM phone_numbers WHERE id = upp.phone_number_id)) OR
                      (m.to_number IN (SELECT number FROM phone_numbers WHERE id = upp.phone_number_id))
                  )
                  WHERE upp.user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent messages for a user with cleaned content
     */
    public function getRecentMessages($user_id, $limit = 10) {
        $query = "SELECT m.*, pn.friendly_name as phone_name
                  FROM " . $this->table_name . " m 
                  LEFT JOIN phone_numbers pn ON (m.to_number = pn.number OR m.from_number = pn.number)
                  LEFT JOIN user_phone_permissions upp ON pn.id = upp.phone_number_id 
                  WHERE upp.user_id = :user_id
                  ORDER BY m.created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Clean any message bodies that might still be encoded
        foreach ($messages as &$message) {
            $message['message_body'] = self::cleanMessageContent($message['message_body']);
        }
        
        return $messages;
    }
}
?>