<?php
/**
 * Gatekeeper Script
 * Validates the signature and timestamp, then streams the file.
 */

// CONFIGURATION
$STORAGE_DIR = __DIR__ . '/../backend/storage/'; // Must match ChartService storageDir
$SECRET_KEY = 'YOUR_SUPER_SECRET_KEY';           // Must match ChartService secret

$file = $_GET['file'] ?? '';
$expires = $_GET['expires'] ?? 0;
$sig = $_GET['sig'] ?? '';

// 1. Check Expiry
if (time() > $expires) {
    http_response_code(410); // Gone
    die("<h1>Link Expired</h1><p>This download link was only valid for 1 hour.</p>");
}

// 2. Validate Signature
$expectedSig = hash_hmac('sha256', $file . $expires, $SECRET_KEY);

if (!hash_equals($expectedSig, $sig)) {
    http_response_code(403); // Forbidden
    die("<h1>Access Denied</h1><p>Invalid security signature.</p>");
}

// 3. Sanitize Path (Prevent Directory Traversal)
// We only allow alphanumeric, dots, underscores, and hyphens in filenames
$safeFile = basename($file);
if ($safeFile !== $file || preg_match('/[^a-zA-Z0-9._-]/', $safeFile)) {
    die("Invalid filename.");
}

$fullPath = $STORAGE_DIR . $safeFile;

if (!file_exists($fullPath)) {
    http_response_code(404);
    die("File not found (it may have been auto-deleted).");
}

// 4. Stream File
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = ($ext === 'pdf') ? 'application/pdf' : 'image/png';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $safeFile . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
exit;
?>