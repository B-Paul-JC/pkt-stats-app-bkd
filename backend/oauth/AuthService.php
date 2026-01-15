<?php
require_once '../db-worker/Database.php';

class AuthService {
    private $conn;

    public function __construct() {
        $database = new Database(["db_name" => "oauth"]);
        $this->conn = $database->getConnection();
        
        // Start Session Securely
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    /**
     * Login using UID and Password
     * Logic: Verify Bcrypt hash
     */
    public function login($uid, $password) {
        $sql = "SELECT id, uid, password_hash, role, profile_id FROM users WHERE uid = :uid LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Secure Password Verification
            // This checks the input password against the stored Bcrypt hash
            if (password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id']; // Internal DB ID
                $_SESSION['uid'] = $user['uid'];     // Public UID
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_id'] = $user['profile_id'];

                return [
                    'success' => true, 
                    'user' => [
                        'id' => $user['id'],
                        'uid' => $user['uid'],
                        'role' => $user['role']
                    ]
                ];
            }
        }

        return ['success' => false, 'message' => 'Invalid UID or password ' . $password];
    }

    public function logout() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        return ['success' => true];
    }

    public function checkSession() {
        if (isset($_SESSION['user_id'])) {
            return [
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'uid' => $_SESSION['uid'],
                    'role' => $_SESSION['role']
                ]
            ];
        }
        return ['authenticated' => false];
    }
    
    /**
     * Register using UID and Password
     * Stores password using Bcrypt
     */
    public function register($uid, $password, $profileId = null) {
        // Validation
        if (empty($uid) || empty($password)) {
            return ['success' => false, 'message' => 'UID and Password are required'];
        }

        // Secure Hashing: Bcrypt (Default)
        // Note: Salting is handled automatically by this function
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (uid, password_hash, profile_id) VALUES (:uid, :hash, :pid)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->bindParam(':hash', $hash);
        $stmt->bindParam(':pid', $profileId);
        
        try {
            $stmt->execute();
            return ['success' => true, 'message' => 'User registered successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'UID already exists'];
        }
    }
}
?>