<?php
function handleCors() {
    // Allow from localhost:5173 or your specific frontend URL
    $origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173' ?? 'http://192.168.3.83:5173/';

    // Headers required for Cookies/Sessions to work across ports
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}
?>