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
            if (!isset($input['uid'], $input['password'])) {
                throw new Exception("Missing credentials");
            }
            echo json_encode($auth->login($input['uid'], $input['password']));
            break;

        case 'logout':
            echo json_encode($auth->logout());
            break;

        case 'check':
            echo json_encode($auth->checkSession());
            break;
            
        case 'register':
            // Only strictly for dev/testing use!
            if (!isset($input['uid'], $input['password'])) {
                throw new Exception("Missing data");
            }
            echo json_encode($auth->register($input['uid'], $input['password']));
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>