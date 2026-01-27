<?php
class Database {
    // Default configuration
    private $host = "192.168.3.83";
    private $db_name = "student_ui_portal";
    private $username = "root";
    private $password = "password"; 
    public $conn;

    /**
     * Constructor accepts optional configuration override
     * Usage: new Database(['db_name' => 'other_db', 'username' => 'admin'])
     */
    public function __construct($config = []) {
        if (isset($config['host'])) $this->host = $config['host'];
        if (isset($config['db_name'])) $this->db_name = $config['db_name'];
        if (isset($config['username'])) $this->username = $config['username'];
        if (isset($config['password'])) $this->password = $config['password'];
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            throw new Exception("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>