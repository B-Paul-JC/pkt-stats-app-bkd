<?php
require_once '../db-worker/Database.php';
require_once __DIR__ . '/Mailer.php';

class AuthService
{
    private $conn;

    public function __construct()
    {
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

        // Initialize OTP store if not present
        if (!isset($_SESSION['otp_store'])) {
            $_SESSION['otp_store'] = [];
        }
    }

    /**
     * Login using UID and Password
     * Logic: Verify Bcrypt hash
     */
    public function login($email, $password)
    {
        $sql = "SELECT id, uid, email, password_hash, role, profile_id, department_id, faculty_id FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        file_put_contents("debug_query.sql", $stmt->queryString . "\nWith Email: " . $email);

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Secure Password Verification
            if (password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['uid'] = $user['uid'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_id'] = $user['profile_id'];
                $_SESSION['department_id'] = $user['department_id'];
                $_SESSION['faculty_id'] = $user['faculty_id'];

                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'uid' => $user['uid'],
                        'role' => $user['role'],
                        'department_id' => $user['department_id'],
                        'faculty_id' => $user['faculty_id']
                    ]
                ];
            }
        }

        return ['success' => false, 'message' => 'Invalid UID or password'];
    }

    public function logout()
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
        return ['success' => true];
    }

    public function checkSession()
    {
        if (isset($_SESSION['user_id'])) {
            return [
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'email' => $_SESSION['email'],
                    'uid' => $_SESSION['uid'],
                    'role' => $_SESSION['role'],
                    'department_id' => $_SESSION['department_id'],
                    'faculty_id' => $_SESSION['faculty_id']
                ]
            ];
        }
        return ['authenticated' => false];
    }

    /**
     * Register using Email and Password
     */
    public function register($email, $password, $profileId = null)
    {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and Password are required'];
        }

        // Generate Unique UID
        $uid = 'U-' . strtoupper(bin2hex(random_bytes(4)));
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (email, uid, password_hash, profile_id) VALUES (:email, :uid, :hash, :pid)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':uid', $uid);
        $stmt->bindParam(':hash', $hash);
        $stmt->bindParam(':pid', $profileId);

        try {
            $stmt->execute();
            return ['success' => true, 'message' => 'User registered successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
    }

    /**
     * REQUEST RESET: Step 1
     * Checks if email exists and generates a time-based OTP
     */
    public function requestReset($email)
    {
        // 1. Check if email exists in DB
        // Assuming 'email' column exists in users table
        $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            return ['success' => false, 'message' => 'Email address not found'];
        }

        // 2. Generate OTP (6 digits)
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = time() + (5 * 60); // 5 minutes from now

        // 3. Store in Session using Email as key
        $_SESSION['otp_store'][$email] = [
            'code' => $code,
            'expires' => $expires
        ];

        // 4. Send Email using Mailer Class
        $mailer = new Mailer();
        $mailSent = $mailer->sendOtp($email, $code);

        if ($mailSent) {
            return ['success' => true, 'message' => 'Verification code sent to your email.'];
        } else {
            // Fallback for development/error logging
            file_put_contents("mails.txt", " [MAIL ERROR] Could not send OTP to $email. Code: $code ");
            return ['success' => false, 'message' => 'Could not send email. Please contact support.'];
        }
    }

    /**
     * VERIFY OTP: Step 2
     * Checks if the OTP is valid for the given email
     */
    public function verifyOtp($email, $otp)
    {
        if (!isset($_SESSION['otp_store'][$email])) {
            return ['success' => false, 'message' => 'No active reset request found.'];
        }

        $record = $_SESSION['otp_store'][$email];

        if (time() > $record['expires']) {
            unset($_SESSION['otp_store'][$email]);
            return ['success' => false, 'message' => 'Code has expired. Please request a new one.'];
        }

        if ($record['code'] !== $otp) {
            return ['success' => false, 'message' => 'Invalid verification code.'];
        }

        return ['success' => true, 'message' => 'Code verified successfully.'];
    }

    /**
     * RESET PASSWORD: Step 3
     * Updates the password for the email
     */
    public function resetPassword($email, $otp, $newPassword)
    {
        // 1. Verify OTP again
        $verification = $this->verifyOtp($email, $otp);
        if (!$verification['success']) {
            return $verification;
        }

        if (empty($newPassword)) {
            return ['success' => false, 'message' => 'New password cannot be empty.'];
        }

        // 2. Hash the new password
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        // 3. Update Database
        $sql = "UPDATE users SET password_hash = :hash WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':hash', $hash);
        $stmt->bindParam(':email', $email);

        if ($stmt->execute()) {
            // 4. Clear the OTP
            unset($_SESSION['otp_store'][$email]);
            return ['success' => true, 'message' => 'Password has been reset successfully.'];
        }

        return ['success' => false, 'message' => 'Database error: Could not update password.'];
    }
}
