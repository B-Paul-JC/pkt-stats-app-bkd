<?php
require_once __DIR__ . '/cors_helper.php';
require_once __DIR__ . '/AuthService.php';

handleCors();
header("Content-Type: application/json");

$auth = new AuthService();
$action = $_GET['action'] ?? '';

// Get JSON Body
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        case 'login':
            if (!isset($input['email'], $input['password'])) {
                throw new Exception("Missing credentials");
            }
            echo json_encode($auth->login($input['email'], $input['password']));
            break;

        case 'logout':
            echo json_encode($auth->logout());
            break;

        case 'check':
            echo json_encode($auth->checkSession());
            break;

        case 'register':
            if (!isset($input['email'], $input['password'])) {
                throw new Exception("Missing data");
            }
            echo json_encode($auth->register($input['email'], $input['password']));
            break;

        // --- Password Reset Routes (Email Based) ---

        case 'request-reset':
            if (!isset($input['email'])) {
                throw new Exception("Email is required");
            }
            echo json_encode($auth->requestReset($input['email']));
            break;

        case 'verify-otp':
            if (!isset($input['email'], $input['otp'])) {
                throw new Exception("Email and OTP are required");
            }
            echo json_encode($auth->verifyOtp($input['email'], $input['otp']));
            break;

        case 'reset-password':
            if (!isset($input['email'], $input['otp'], $input['newPassword'])) {
                throw new Exception("Missing required fields");
            }
            echo json_encode($auth->resetPassword($input['email'], $input['otp'], $input['newPassword']));
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
