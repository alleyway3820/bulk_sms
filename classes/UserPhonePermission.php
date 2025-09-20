<?php
// classes/UserPhonePermission.php
class UserPhonePermission {
    private $conn;
    private $table_name = "user_phone_permissions";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function assignNumber($user_id, $phone_number_id, $can_send = true, $can_receive = true) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, phone_number_id, can_send, can_receive) 
                  VALUES (:user_id, :phone_number_id, :can_send, :can_receive)
                  ON DUPLICATE KEY UPDATE can_send = :can_send, can_receive = :can_receive";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":phone_number_id", $phone_number_id);
        $stmt->bindParam(":can_send", $can_send, PDO::PARAM_BOOL);
        $stmt->bindParam(":can_receive", $can_receive, PDO::PARAM_BOOL);
        
        return $stmt->execute();
    }

    public function removePermission($user_id, $phone_number_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id AND phone_number_id = :phone_number_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":phone_number_id", $phone_number_id);
        return $stmt->execute();
    }

    public function getUserPermissions($user_id) {
        $query = "SELECT upp.*, pn.number, pn.friendly_name 
                  FROM " . $this->table_name . " upp 
                  JOIN phone_numbers pn ON upp.phone_number_id = pn.id 
                  WHERE upp.user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>