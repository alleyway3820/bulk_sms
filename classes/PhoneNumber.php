<?php
// classes/PhoneNumber.php
class PhoneNumber {
    private $conn;
    private $table_name = "phone_numbers";

    public $id;
    public $number;
    public $friendly_name;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (number, friendly_name, is_active) 
                  VALUES (:number, :friendly_name, :is_active)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":number", $this->number);
        $stmt->bindParam(":friendly_name", $this->friendly_name);
        $stmt->bindParam(":is_active", $this->is_active);
        
        return $stmt->execute();
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserNumbers($user_id) {
        $query = "SELECT pn.*, upp.can_send, upp.can_receive 
                  FROM " . $this->table_name . " pn 
                  JOIN user_phone_permissions upp ON pn.id = upp.phone_number_id 
                  WHERE upp.user_id = :user_id AND pn.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET number = :number, friendly_name = :friendly_name, is_active = :is_active 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":number", $this->number);
        $stmt->bindParam(":friendly_name", $this->friendly_name);
        $stmt->bindParam(":is_active", $this->is_active);
        
        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}
?>