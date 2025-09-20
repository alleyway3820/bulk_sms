<?php
// config/database.php - Updated for environment variables with emoji support
require_once __DIR__ . '/env_loader.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;

    public function __construct() {
        // Load from environment variables with fallbacks for sms_sms
        $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'sms_sms';
        $this->username = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'sms_sms';
        $this->password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $this->port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            // UPDATED: Add charset=utf8mb4 for emoji support
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // CRITICAL: Set charset to utf8mb4 for emoji support
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }
        return $this->conn;
    }
}
?>