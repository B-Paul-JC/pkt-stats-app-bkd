<?php
// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$json_input = file_get_contents('php://input');

if (empty($json_input)) {
    echo "Error: No data received.";
    exit;
}

$python_command = "python render_reports.py"; // Ensure 'python' is in PATH and has fpdf2 installed

$descriptorspec = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"], // stdout (binary pdf)
    2 => ["pipe", "w"]  // stderr
];

$process = proc_open($python_command, $descriptorspec, $pipes);

if (is_resource($process)) {
    fwrite($pipes[0], $json_input);
    fclose($pipes[0]);

    $pdf_content = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $return_value = proc_close($process);

    if (!empty($errors)) {
        http_response_code(500);
        // Log error server-side or send text response
        header('Content-Type: text/plain');
        echo "Python Error: " . $errors;
    } else {
        // Send PDF Headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="report.pdf"');
        header('Content-Length: ' . strlen($pdf_content));
        echo $pdf_content;
    }
} else {
    http_response_code(500);
    echo "Failed to start Python process.";
}
