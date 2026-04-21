<?php
// upload_handler.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Since file uploads use multipart/form-data, use $_POST instead of php://input
$action = $_POST['action'] ?? '';

if ($action === 'upload_data') {
    $targetType = $_POST['type'] ?? 'students'; // Expected: 'students' or 'staff'

    if (!isset($_FILES['csv_file'])) {
        echo json_encode(["error" => "No file uploaded. Please select a CSV file."]);
        exit();
    }

    // --- TODO: Update Schema Validation ---
    // Define the mandatory columns expected for each type to match your DB schema
    $expectedSchemas = [
        'students' => ['gender', 'level_id', 'department_id', 'hall_id', 'state_of_origin'],
        'staff' => ['staffNumber', 'staffFirstName', 'staffLastName', 'staffGender', 'staffDepartment']
    ];

    // Read just the first line (headers) of the uploaded file
    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $headers = fgetcsv($handle, 1000, ",");
    fclose($handle);

    if (!$headers) {
        http_response_code(400);
        echo json_encode(["error" => "Could not read CSV headers. File might be empty or corrupted."]);
        exit();
    }

    // Clean whitespace from headers and check for missing mandatory columns
    $headers = array_map('trim', $headers);
    $missingColumns = array_diff($expectedSchemas[$targetType], $headers);

    if (!empty($missingColumns)) {
        http_response_code(400);
        echo json_encode([
            "error" => "Schema mismatch. Missing required columns: " . implode(", ", $missingColumns) . ". Please ensure your CSV headers exactly match the database schema."
        ]);
        exit();
    }
    // --- END NEW: Schema Validation ---

    // Ensure the uploads directory exists securely in the same folder
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Standardize the filename so the APIs know exactly where to look for the active dataset
    $targetFile = $uploadDir . $targetType . '_latest.csv';

    // Move the uploaded file
    if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $targetFile)) {
        echo json_encode([
            "success" => true,
            "message" => ucfirst($targetType) . " data uploaded successfully and is now active."
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to save the uploaded file to the server."]);
    }
    exit();
}

echo json_encode(["error" => "Invalid action specified."]);
