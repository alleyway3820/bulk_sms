<?php
// classes/ApiSettings.php
class ApiSettings {
    private $conn;
    private $table_name = "api_settings";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function get() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE is_active = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($api_username, $api_password, $webhook_url) {
        $query = "UPDATE " . $this->table_name . " 
                  SET api_username = :api_username, api_password = :api_password, webhook_url = :webhook_url 
                  WHERE is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":api_username", $api_username);
        $stmt->bindParam(":api_password", $api_password);
        $stmt->bindParam(":webhook_url", $webhook_url);
        
        return $stmt->execute();
    }
}
?>