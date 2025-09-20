<?php
// classes/Message.php - Fixed version with correct BulkVS API format
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

    public function create() {
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
     * FIXED: Send SMS using correct BulkVS GET method format
     */
    public function sendSMS($api_username, $api_password) {
        // Clean phone numbers to 10 digits only
        $from_clean = preg_replace('/\D/', '', $this->from_number);
        $to_clean = preg_replace('/\D/', '', $this->to_number);
        
        // Create basic auth credentials
        $credentials = base64_encode($api_username . ':' . $api_password);
        
        // Build GET URL with query parameters - THIS IS THE CORRECT FORMAT
        $params = [
            'to' => $to_clean,
            'from' => $from_clean,
            'message' => $this->message_body
        ];
        
        $url = "https://portal.bulkvs.com/api/v1.0/messageSend?" . http_build_query($params);
        
        // Initialize cURL
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',  // Explicitly use GET method
            CURLOPT_HTTPHEADER => [
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

        // Log the attempt for debugging
        error_log("BulkVS API Call: URL=$url, HTTP=$httpCode, Response=$response, Error=$curl_error");

        if ($httpCode == 200) {
            $this->status = 'sent';
            $this->bulkvs_message_id = $response; // BulkVS may return message ID
            
            // Update status in database
            $this->updateStatus();
            
            return true;
        } else {
            $this->status = 'failed';
            $this->updateStatus();
            
            // Log the failure
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
                      SET status = :status, bulkvs_message_id = :bulkvs_message_id 
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
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
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
}
?>