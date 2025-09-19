<?php
// classes/Message.php
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
                  JOIN user_phone_permissions upp ON pn.id = upp.phone_number_id 
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

    public function sendSMS($api_username, $api_password) {
        // Send SMS via BulkVS API
        $credentials = base64_encode($api_username . ':' . $api_password);
        
        $data = [
            'to' => preg_replace('/\D/', '', $this->to_number),
            'from' => preg_replace('/\D/', '', $this->from_number),
            'message' => $this->message_body,
            'method' => 'post'
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://portal.bulkvs.com/api/v1.0/messageSend",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $credentials
            ]
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode == 200) {
            $this->status = 'sent';
            $this->bulkvs_message_id = $response;
            
            // Update status in database
            $query = "UPDATE " . $this->table_name . " SET status = :status, bulkvs_message_id = :bulkvs_message_id WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":status", $this->status);
            $stmt->bindParam(":bulkvs_message_id", $this->bulkvs_message_id);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();
            
            return true;
        } else {
            $this->status = 'failed';
            return false;
        }
    }
}
?>