<?php
class Database {
    private $host = "localhost";
    private $db_name = "student_ui_portal";
    private $username = "root";
    private $password = ""; // Add your password if set
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            // Log error in a real app, don't expose to user
            throw new Exception("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>