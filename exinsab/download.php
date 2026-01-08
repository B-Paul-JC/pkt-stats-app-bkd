<?php
// Start output buffering immediately to catch any stray whitespace or includes
ob_start();

// CONFIGURATION
// Use realpath to strictly define where the storage is.
// Ensure this path resolves correctly relative to THIS file.
$baseStorage = __DIR__ . '/../backend/storage';
$STORAGE_DIR = realpath($baseStorage);

$SECRET_KEY = 'YOUR_SUPER_SECRET_KEY'; // MUST match the key in index.php

// Helper to kill script cleanly
function stop($code, $msg) {
    ob_end_clean(); // Clear buffer
    http_response_code($code);
    die($msg);
}

// 1. Validation: Storage Directory
if (!$STORAGE_DIR || !is_dir($STORAGE_DIR)) {
    // If realpath fails, try to create it or error out
    stop(500, "Storage directory configuration error.");
}

// --- NEW TOKEN DECODING LOGIC ---
$token = $_GET['token'] ?? '';

if (!$token) {
    stop(400, "Missing download token.");
}

// 1. Decode the outer token
$decoded = base64_decode($token, true);
if ($decoded === false) {
    stop(400, "Invalid token format.");
}

// 2. Split into parts: [0]=>Base64Filename, [1]=>Expires, [2]=>Signature
$parts = explode('|', $decoded);
if (count($parts) !== 3) {
    stop(400, "Corrupted token data.");
}

list($b64File, $expires, $sig) = $parts;

// 3. Reconstruct payload to verify signature
// The payload we signed was: Base64Filename|Expires
$payloadToCheck = $b64File . '|' . $expires;
$expectedSig = hash_hmac('sha256', $payloadToCheck, $SECRET_KEY);

if (!hash_equals($expectedSig, $sig)) {
    stop(403, "Access Denied: Invalid Signature.");
}

// 4. Check Expiry
if (time() > $expires) {
    stop(410, "Link Expired.");
}

// 5. Decode Filename and Sanitize
$file = base64_decode($b64File);
if ($file === false) {
    stop(400, "Invalid filename encoding.");
}

$safeFile = basename($file);
if ($safeFile !== $file || preg_match('/[^a-zA-Z0-9._-]/', $safeFile)) {
    stop(400, "Invalid Filename.");
}

// --- END NEW LOGIC ---

// 5. Check File Existence
$fullPath = $STORAGE_DIR . DIRECTORY_SEPARATOR . $safeFile;

if (!file_exists($fullPath)) {
    stop(404, "File not found. " . $fullPath);
}

// 6. Serve File
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = ($ext === 'pdf') ? 'application/pdf' : 'image/png';
$size = filesize($fullPath);

// Clear any previous output (warnings, spaces, echoes)
ob_end_clean(); 

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
// Using 'inline' allows preview. If this continues to fail, change 'inline' to 'attachment'
header('Content-Disposition: inline; filename="' . $safeFile . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

if ($size) {
    header('Content-Length: ' . $size);
}

readfile($fullPath);
exit;
?>